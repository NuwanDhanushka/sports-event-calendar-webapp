<script setup>
import { computed, onMounted, ref, watch } from "vue";
import InputCalendar from "../components/InputCalendar.vue";
import moment from "moment-timezone";
import { listEvents } from "../api/events.js";
import { listSports } from "../api/sports.js";

const timeZone = "Europe/Vienna";

const today = moment.tz(timeZone);    // today/view

const viewYear = ref(today.year());
const viewMonth = ref(today.month()); // 0–11
const viewMode = ref('month');  // 'month' | 'week' | 'day'
const selectedDayKey = ref(''); // 'YYYY-MM-DD'

/** Timeline vertical sizing */
const HOUR_PX = 64;                   // for height per hour row  - h-16 (16*4 = 64px)
const TIMELINE_PX = HOUR_PX * 24;     // full-day timeline height - 1536px

/** Filters */
const searchText = ref('');
const selectedSports = ref([]); // ['Run', 'Football']
const dateRange = ref(null);    // "YYYY-MM-DD/YYYY-MM-DD" or {start,end}

/** For toggle limit events to current view date range vs fetch all */
const globalEvents = ref(false);
const globalLabel = computed(() =>
    globalEvents.value ? 'All events' : 'Only in current view'
);

/** Api data and loading state */
const rawEvents = ref([]);
const loading = ref(true);
const apiError = ref("");
const storagePublicBase = ref("/storage/");
const sports = ref([]);

/** Titles */
const monthTitle = computed(() =>
    moment.tz({year: viewYear.value, month: viewMonth.value, date: 1}, timeZone).format('MMMM YYYY')
);

const weekTitle = computed(() => {
  const start = moment.tz(selectedKeyOrFirst.value, 'YYYY-MM-DD', timeZone).startOf('isoWeek');
  const end = start.clone().endOf('isoWeek');
  const fmt = (m) => m.format('MMM D, YYYY');
  return `${fmt(start)} – ${fmt(end)}`;
});

const dayTitle = computed(() =>
    moment.tz(selectedKeyOrFirst.value, 'YYYY-MM-DD', timeZone).format('D MMMM YYYY')
);

/** Header text */
const headerText = computed(() => {
  if (viewMode.value === 'month') return monthTitle.value;
  if (viewMode.value === 'week') return weekTitle.value;
  return dayTitle.value;
});

/** Base date key used when no explicit selected day */
 const selectedKeyOrFirst = computed(() =>
    selectedDayKey.value ||
    moment.tz({year: viewYear.value, month: viewMonth.value, date: 1}, timeZone).format('YYYY-MM-DD')
);

/** Event mapping and normalization */

/** Map raw api data to ui friendly format */
function mapApiEvent(api) {
  const mStart = api.startAt
      ? moment.tz(api.startAt, "YYYY-MM-DD HH:mm:ss", timeZone)
      : null;
  const mEnd = api.endAt
      ? moment.tz(api.endAt, "YYYY-MM-DD HH:mm:ss", timeZone)
      : null;

  return {
    // ids & core data
    id: api.id,
    title: api.title ?? "",
    description: api.description ?? null,
    status: api.status ?? null,
    bannerPath: api.bannerPath ?? null,

    // normalized datetimes (ISO) used by UI
    start_at: mStart ? mStart.toISOString() : null,
    end_at:   mEnd   ? mEnd.toISOString()   : null,

    //original datetimes (local)
    startAt: api.startAt ?? null,
    endAt:   api.endAt   ?? null,

    // sport
    sport: api.sport || null,                              // keep full object
    sport_id: api.sport?.id ?? api.sportId ?? null,        // robust id
    category: api.sport?.name ?? null,                     // name alias

    // foreign key ids (for filters/links)
    competition_id: api.competitionId ?? api.competition?.id ?? null,
    venue_id:       api.venueId       ?? api.venue?.id       ?? null,
    created_by_id:  api.createdById   ?? api.createdBy?.id   ?? null,

    // date-only all-day handling
    all_day: !!api.date && !api.startAt && !api.endAt,
    date: api.date ?? null,

    // nested objects as-is
    venue: api.venue || null,
    competition: api.competition || null,
    created_by: api.createdBy || null,
    teams: Array.isArray(api.teams) ? api.teams : [],
  };
}

/** Normalized mapped event data into a structure that can be used in calander UI. */
function normalizeEvent(e) {
  const base = {...e};

  if (e.all_day || e.date) {
    const day =
        e.date ??
        moment.parseZone(e.start_at ?? e.end_at).tz(timeZone).format("YYYY-MM-DD");
    const start = moment.tz(day, timeZone).startOf("day");
    const end = moment.tz(day, timeZone).endOf("day");
    return {
      ...base,
      isAllDay: true,
      mStart: start,
      mEnd: end,
      daySpan: 1,
      keyStart: start.format("YYYY-MM-DD"),
      keyEnd: end.format("YYYY-MM-DD"),
    };
  }

  /** timed event */
  const mStart = e.start_at ? moment.parseZone(e.start_at).tz(timeZone) : null;
  const mEnd = e.end_at ? moment.parseZone(e.end_at).tz(timeZone) : mStart;
  const startDay = mStart ? mStart.clone().startOf("day") : null;
  const endDay = mEnd ? mEnd.clone().startOf("day") : startDay;

  return {
    ...base,
    isAllDay: false,
    mStart,
    mEnd,
    daySpan: startDay && endDay ? endDay.diff(startDay, "days") + 1 : 1,
    keyStart: mStart ? mStart.format("YYYY-MM-DD") : null,
    keyEnd: mEnd ? mEnd.format("YYYY-MM-DD") : null,
  };
}

const normEvents = computed(() => rawEvents.value.map(normalizeEvent));


/** Data range Helpers */

/** Current visible date range based on view mode. */
function viewDateRange() {
  if (viewMode.value === "day") {
    const m = moment.tz(selectedKeyOrFirst.value, "YYYY-MM-DD", timeZone);
    return { from: m.clone().startOf("day"), to: m.clone().endOf("day") };
  }
  if (viewMode.value === "week") {
    const start = moment
        .tz(selectedKeyOrFirst.value, "YYYY-MM-DD", timeZone)
        .startOf("isoWeek");
    const end = start.clone().endOf("isoWeek");
    return { from: start, to: end };
  }

  const first = moment.tz(
      { year: viewYear.value, month: viewMonth.value, date: 1 },
      timeZone
  );
  const start = first.clone().startOf("isoWeek");
  const end = first.clone().endOf("month").endOf("isoWeek");
  return { from: start, to: end };
}

/** Extract date_from / date_to from dateRange or fall back to viewDateRange.
 * Also attaches search & sports filters used by API.
 *
 */
function currentParamsForAPI() {
  const params = {};

  if (!globalEvents.value) {
    let from, to;
    if (dateRange.value) {
      const s = typeof dateRange.value === 'string'
          ? (dateRange.value.split('/')[0] || '')
          : (dateRange.value.start || '');
      const e = typeof dateRange.value === 'string'
          ? (dateRange.value.split('/')[1] || '')
          : (dateRange.value.end || '');
      from = s || null;
      to   = e || null;
    }
    if (!from || !to) {
      const { from: vFrom, to: vTo } = viewDateRange();
      from = vFrom.format('YYYY-MM-DD');
      to   = vTo.format('YYYY-MM-DD');
    }
    params.date_from = from;
    params.date_to   = to;
  }

  const q = (searchText.value || '').trim();
  if (q) params.q = q;

  if (selectedSports.value?.length) {
    params.sports = selectedSports.value; // ids
  }
  return params;
}

/** Fetch events based on current view / filters.*/
async function fetchEventsForCurrentView() {
  loading.value = true;
  apiError.value = "";
  try {
    const params = currentParamsForAPI();
    const res = await listEvents(params);
    const payload = res?.data ?? res;

    const events = (payload?.data?.events || payload?.events || []).map(mapApiEvent);
    rawEvents.value = events;

    const meta = payload?.data?.meta || payload?.meta || {};
    if (meta.storagePublicBase) storagePublicBase.value = meta.storagePublicBase;
  } catch (e) {
    apiError.value = String(e?.message || e);
  } finally {
    loading.value = false;
  }
}

/**
 * Fetch sports
 */
async function fetchSports() {
  try {
    const res = await listSports({ all: 1, team_only: 1 });
    const payload = res?.data ?? res;
    sports.value = payload?.data?.sports || payload?.sports || [];
  } catch (e) {
    console.error('Failed to load sports', e);
  }
}

/** Derived filter values */

/** Normalize datefrom / dateto to from data range into simple strings */
const dateFrom = computed(() => {
  if (!dateRange.value) return '';
  return typeof dateRange.value === 'string'
      ? (dateRange.value.split('/')[0] || '')
      : (dateRange.value.start || '');
});

const dateTo = computed(() => {
  if (!dateRange.value) return '';
  return typeof dateRange.value === 'string'
      ? (dateRange.value.split('/')[1] || '')
      : (dateRange.value.end || '');
});

/** Normalized selected sports as numbers / strings for  matching */
 const selectedSportsNums = computed(() =>
    (selectedSports.value || []).map(v => Number(v)).filter(v => !Number.isNaN(v))
);
const selectedSportsStrs = computed(() =>
    (selectedSports.value || []).map(v => String(v).trim()).filter(Boolean)
);

/** Filter predicated */

/** check if event matches search text */
function titleMatches(ev) {
  const q = searchText.value.trim().toLowerCase();
  return !q || String(ev.title || '').toLowerCase().includes(q);
}

/** check if event sport matches selected sports */
function sportMatches(ev) {
  const sel = selectedSports.value;
  if (!sel?.length) return true;

  const evId   = ev.sport_id ?? ev.sportId ?? ev.sport?.id ?? null;
  const evName = (ev.category || ev.sport?.name || '').trim();

  if (selectedSportsNums.value.length) {
    return evId != null && selectedSportsNums.value.includes(Number(evId));
  }
  return !!evName && selectedSportsStrs.value.includes(evName);
}

/**
 * Check if event is within selected date range.
 * Works for both all day & timed events using keyStart/keyEnd.
 */
function inDateRange(ev) {
  // If no range always match
  if (!dateFrom.value && !dateTo.value) return true;

  // Resolve event start/end as YYYY-MM-DD strings
  const evStart = ev.keyStart || ev.mStart?.format?.('YYYY-MM-DD') || ev.date || '';
  const evEnd = ev.keyEnd || ev.mEnd?.format?.('YYYY-MM-DD') || evStart;

  if (!evStart) return false;

  // If only one bound provided, treat as open ended
  const from = dateFrom.value || '0000-01-01';
  const to = dateTo.value || '9999-12-31';

  return evStart <= to && evEnd >= from;
}

/**
 * True when any of the filters are active.
 * When true, a grouped list view is shown instead of full grid.
 */
const isFilterActive = computed(() =>
    !!searchText.value.trim() ||
    (selectedSports.value?.length || 0) > 0 ||
    !!dateFrom.value || !!dateTo.value
);

/** Full filtered set used in list view and per-day grouping */
const filteredEvents = computed(() =>
    normEvents.value.filter(ev => titleMatches(ev) && sportMatches(ev) && inDateRange(ev))
);

/**
 * Map of dayKey -> events for that day (used for Month/Week/Day layouts).
 * Uses either normalized list or filtered list depending on isFilterActive.
 */
const eventsByDay = computed(() => {
  const map = new Map();
  const source = isFilterActive.value ? filteredEvents.value : normEvents.value;

  for (const ev of source) {
    const startDay = (ev.mStart ?? moment.tz(ev.date, timeZone)).clone().startOf("day");
    const endDay = (ev.mEnd ?? startDay).clone().startOf("day");
    const cursor = startDay.clone();

    // Put multi-day events into each day bucket they span
    while (cursor.isSameOrBefore(endDay, "day")) {
      const key = cursor.format("YYYY-MM-DD");
      if (!map.has(key)) map.set(key, []);
      map.get(key).push(ev);
      cursor.add(1, "day");
    }
  }

  // Sort per-day events: all-day first, then by start time, then title
  for (const [, list] of map.entries()) {
    list.sort((a, b) => {
      if (a.isAllDay !== b.isAllDay) return a.isAllDay ? -1 : 1;
      const ta = a.mStart ? a.mStart.valueOf() : Number.POSITIVE_INFINITY;
      const tb = b.mStart ? b.mStart.valueOf() : Number.POSITIVE_INFINITY;
      return ta !== tb ? ta - tb : String(a.title).localeCompare(String(b.title));
    });
  }

  return map;
});

/** listview flatten events to date,time,event and sort */
const listSorted = computed(() => {
  const items = filteredEvents.value.map(ev => ({
    key: ev.keyStart || ev.mStart?.format?.('YYYY-MM-DD') || ev.date || '',
    t: ev.isAllDay ? '00:00' : (ev.mStart?.format?.('HH:mm') || '00:00'),
    ev
  }));
  items.sort((a, b) => a.key === b.key ? a.t.localeCompare(b.t) : a.key.localeCompare(b.key));
  return items;
});

/**
 * Group list view items by date to render date sections.
 */
const listGrouped = computed(() => {
  const out = [];
  let cur = '';
  let bucket = [];
  for (const it of listSorted.value) {
    if (it.key !== cur) {
      if (bucket.length) out.push({key: cur, items: bucket});
      cur = it.key;
      bucket = [it];
    } else bucket.push(it);
  }
  if (bucket.length) out.push({key: cur, items: bucket});
  return out;
});

/**
 * label for list-group headings.
 */
function fmtDateLabel(key) {
  return key ? moment.tz(key, 'YYYY-MM-DD', timeZone).format('dddd, D MMMM YYYY') : '';
}

/** Calendar grid helpers (Month / Week / Day) */

const weekdayLabels = computed(() => {
  const start = moment.tz({year: 2021, month: 0, date: 4}, timeZone).startOf("isoWeek");
  return Array.from({length: 7}, (_, i) => start.clone().add(i, "days").format("ddd"));
});

/**
 * 42-cell month grid including leading/trailing days.
 */
const daysGrid = computed(() => {
  const first = moment.tz({year: viewYear.value, month: viewMonth.value, date: 1}, timeZone);
  const start = first.clone().startOf("isoWeek"); // Monday
  const monthIdx = viewMonth.value;
  const now = moment.tz(timeZone);

  return Array.from({length: 42}, (_, i) => {
    const m = start.clone().add(i, "days");
    return {
      date: m.toDate(),
      key: m.format("YYYY-MM-DD"),
      isCurrentMonth: m.month() === monthIdx,
      isToday: m.isSame(now, "day"),
    };
  });
});

/** Selected date as Date object fallbacks to first-of-month */
const selectedDate = computed(() => {
  const baseKey =
      selectedDayKey.value ||
      moment.tz({year: viewYear.value, month: viewMonth.value, date: 1}, timeZone).format("YYYY-MM-DD");
  return moment.tz(baseKey, "YYYY-MM-DD", timeZone).startOf("day").toDate();
});

const weekStart = computed(() => startOfWeekMon(selectedDate.value));

/**
 * Week days grid for week-view header and columns.
 */
const weekDaysGrid = computed(() => {
  const start = moment.tz(weekStart.value, timeZone).startOf("day");
  return Array.from({length: 7}, (_, i) => {
    const m = start.clone().add(i, "days");
    return {key: m.format("YYYY-MM-DD"), date: m.toDate(), name: m.format("ddd"), day: m.date()};
  });
});

/** 24h labels for timelines */
const hours = Array.from({length: 24}, (_, h) => moment({hour: h, minute: 0}).format("HH:mm"));

/** Day header label for day view */
 const dayHeaderLabel = computed(() => {
  const key =
      selectedDayKey.value ||
      moment.tz({year: viewYear.value, month: viewMonth.value, date: 1}, timeZone).format('YYYY-MM-DD');
  return moment.tz(key, 'YYYY-MM-DD', timeZone).format('dddd');
});

/**
 * Build layout blocks for a given day:
 * - allDay: array of all-day events
 * - timed: array of positioned timed blocks (top/height/col)
 * - columns: max concurrency used for width calculation
 */
function dayBlocks(dayKey) {
  const list = eventsByDay.value.get(dayKey) || [];

  const allDay = list.filter(e => e.isAllDay);
  const timed = [];

  const dayStart = moment.tz(dayKey, "YYYY-MM-DD", timeZone).startOf("day");
  const dayEnd = dayStart.clone().endOf("day");

  // Collect timed events and clip them to dayStart, dayEnd
  for (const ev of list) {
    if (ev.isAllDay) continue;
    const s = ev.mStart;
    const e = ev.mEnd || ev.mStart;
    if (!s?.isValid?.()) continue;


    const start = moment.max(s, dayStart);
    const end = moment.min(e, dayEnd);
    if (!end.isAfter(start)) continue;

    const startMin = start.diff(dayStart, "minutes");
    const endMin = Math.max(startMin + 1, end.diff(dayStart, "minutes"));
    const durMin = endMin - startMin;

    timed.push({
      ev,
      _startMin: startMin,
      _endMin: endMin,
      top: (startMin / 1440) * 100,
      height: Math.max(2, (durMin / 1440) * 100),
    });
  }

  // Greedy column packing for overlapping events
  timed.sort((a, b) => a._startMin - b._startMin || a._endMin - b._endMin);

  const lastEndByCol = [];
  for (const b of timed) {
    let col = 0;
    while (col < lastEndByCol.length && lastEndByCol[col] > b._startMin) col++;
    b.col = col;
    lastEndByCol[col] = b._endMin;
    delete b._startMin;
    delete b._endMin;
  }

  const columns = Math.max(1, lastEndByCol.length);
  return {allDay, timed, columns};
}

function prevMonth() {
  const m = moment.tz({year: viewYear.value, month: viewMonth.value, date: 1}, timeZone).subtract(1, "month");
  viewYear.value = m.year();
  viewMonth.value = m.month();
}

function nextMonth() {
  const m = moment.tz({year: viewYear.value, month: viewMonth.value, date: 1}, timeZone).add(1, "month");
  viewYear.value = m.year();
  viewMonth.value = m.month();
}

/** Ensure we always have a selectedDayKey when week/day mode needs it */
 function ensureSelectedDay() {
  if (!selectedDayKey.value) {
    selectedDayKey.value = moment
        .tz({year: viewYear.value, month: viewMonth.value, date: 1}, timeZone)
        .format('YYYY-MM-DD');
  }
}

/**
 * Navigate backwards (month/week/day depending on viewMode).
 */
function prev() {
  if (viewMode.value === 'month') return prevMonth();
  ensureSelectedDay();
  const base = moment.tz(selectedDayKey.value, 'YYYY-MM-DD', timeZone);
  const nextKey = (viewMode.value === 'week'
          ? base.startOf('isoWeek').subtract(1, 'week')
          : base.subtract(1, 'day')
  ).format('YYYY-MM-DD');

  selectedDayKey.value = nextKey;

  const m = moment.tz(nextKey, 'YYYY-MM-DD', timeZone);
  viewYear.value = m.year();
  viewMonth.value = m.month();
}

/**
 * Navigate forwards (month/week/day depending on viewMode).
 */
function next() {
  if (viewMode.value === 'month') return nextMonth();
  ensureSelectedDay();
  const base = moment.tz(selectedDayKey.value, 'YYYY-MM-DD', timeZone);
  const nextKey = (viewMode.value === 'week'
          ? base.startOf('isoWeek').add(1, 'week')
          : base.add(1, 'day')
  ).format('YYYY-MM-DD');

  selectedDayKey.value = nextKey;

  const m = moment.tz(nextKey, 'YYYY-MM-DD', timeZone);
  viewYear.value = m.year();
  viewMonth.value = m.month();
}

/**
 * Change the view mode
 * @param mode
 */
function setMode(mode) {
  if ((mode === 'week' || mode === 'day') && !selectedDayKey.value) {
    selectedDayKey.value = moment
        .tz({year: viewYear.value, month: viewMonth.value, date: 1}, timeZone)
        .format('YYYY-MM-DD');
  }
  viewMode.value = mode;
}

/**
 * Event details modal
 */
const showEvent = ref(false);
const selectedEvent = ref(null);
const imgError = ref(false);

function openEvent(ev) {
  selectedEvent.value = ev;
  imgError.value = false; // reset banner error state on every open
  showEvent.value = true;
}
function closeEvent() {
  showEvent.value = false;
  selectedEvent.value = null;
}

/** Reset banner error state when selected event changes */
watch(() => selectedEvent.value?.id, () => { imgError.value = false });

/**
 * Get the readable time range for an event
 * @param ev
 * @returns {string}
 */
function eventTimeRange(ev) {
  if (!ev) return '';
  if (ev.isAllDay) {
    const day = (ev.keyStart || ev.date) ? moment.tz(ev.keyStart || ev.date, 'YYYY-MM-DD', timeZone) : ev.mStart;
    return `${day.format('ddd, D MMM YYYY')} • All day`;
  }
  const s = ev.mStart ?? (ev.start_at ? moment.parseZone(ev.start_at).tz(timeZone) : null);
  const e = ev.mEnd   ?? (ev.end_at   ? moment.parseZone(ev.end_at).tz(timeZone) : null);
  if (!s) return '';
  return e
      ? `${s.format('ddd, D MMM YYYY HH:mm')} – ${e.format('HH:mm')} (${timeZone})`
      : `${s.format('ddd, D MMM YYYY HH:mm')} (${timeZone})`;
}

/**
 * Get the banner image URL for an event
 * @param ev
 * @returns {string|null}
 */
function bannerUrl(ev) {
  const path = ev?.bannerPath || ev?.event_banner || null;
  return path ? `${storagePublicBase.value.replace(/\/$/,'')}/${String(path).replace(/^\//,'')}` : null;
}

/**
 * Get the event duration from start and end times
 * @param ev
 * @returns {string}
 */
function eventDuration(ev) {
  if (!ev || ev.isAllDay || !ev?.mStart || !ev?.mEnd) return '';
  const mins = ev.mEnd.diff(ev.mStart, 'minutes');
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  return h ? `${h}h${m ? ' ' + m + 'm' : ''}` : `${m}m`;
}

/**
 * Get the team name from sides
 * @param ev
 * @returns {{homeName: *, awayName: *, others: T[]}}
 */
function teamSides(ev) {
  const list = ev?.teams || [];
  const home = list.find(t => (t.side || '').toLowerCase() === 'home') || null;
  const away = list.find(t => (t.side || '').toLowerCase() === 'away') || null;
  const others = list.filter(t => t !== home && t !== away);
  const tn = t => t?.team?.name || '';
  return { homeName: tn(home), awayName: tn(away), others };
}

/**
 * label for competition type
 * @param type
 * @returns {string}
 */
function compTypeLabel(type) {
  return (type || '').replace(/_/g, ' ').replace(/\b\w/g, s => s.toUpperCase());
}

/**
 * Get the Tailwind/DaisyUI class for an event status
 * @param status
 * @returns {string}
 */
function statusToneClass(status) {
  switch ((status || "").toLowerCase()) {
    case "confirmed":
      return "bg-success/70 hover:bg-success/70 border border-success text-success-content";
    case "tentative":
      return "bg-warning/70 hover:bg-warning/70 border border-warning text-warning-content";
    case "scheduled":
      return "bg-info/70 hover:bg-info/70 border border-info text-info-content";
    case "cancelled":
    case "canceled":
      return "bg-error/70 hover:bg-error/70 border border-error text-error-content";
    default:
      return "bg-base-100/70 hover:bg-base-200 border border-base-300";
  }
}

/**
 * Start-end time label for inline chips.
 * @param ev
 * @returns {string|string}
 */
function timeLabel(ev) {
  if (ev?.isAllDay) return "All day";
  if (!ev?.mStart) return "";
  const start = ev.mStart.format("HH:mm");
  const end = ev.mEnd ? ev.mEnd.format("HH:mm") : "";
  return end ? `${start}–${end}` : start;
}

/**
 * Only end time label - used where needed.
 * @param ev
 * @returns {string|string|string}
 */
function timeLabelEnd(ev) {
  if (ev?.isAllDay || !ev?.mEnd) return "";
  const mEnd = ev.mEnd;
  return mEnd?.isValid?.() ? mEnd.format("HH:mm") : "";
}

/**
 * Get start of week for a given date
 * @param d
 * @returns {Date}
 */
function startOfWeekMon(d) {
  return moment.tz(d, timeZone).startOf("isoWeek").toDate();
}

/** Debounced refetch when view or filters change */
let tick;
watch(
    [viewYear, viewMonth, viewMode, selectedDayKey, searchText, selectedSports, dateRange, globalEvents],
    () => { clearTimeout(tick); tick = setTimeout(fetchEventsForCurrentView, 120); } // tiny debounce
);

/** get sports and events for current view when mounted */
onMounted(async () => {
  await fetchSports();
  await fetchEventsForCurrentView();
});

</script>

<template>
  <div class="space-y-4">

    <!-- Loading overlay -->
    <div
        v-if="loading"
        class="absolute inset-0 z-30 flex flex-col items-center justify-center bg-base-100/70 backdrop-blur-sm"
    >
      <span class="loading loading-spinner loading-lg text-info"></span>
      <p class="mt-3 text-sm text-base-content/70">
        Loading events;
      </p>
    </div>

    <!-- Error banner -->
    <div
        v-if="apiError && !loading"
        class="alert alert-error shadow-sm text-sm flex items-center gap-2"
    >
      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24"
           stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a1 1 0 0 0 .86 1.5h18.64a1 1 0 0 0 .86-1.5L13.71 3.86a1 1 0 0 0-1.72 0z"/>
      </svg>
      <span>Failed to load events: {{ apiError }}</span>
    </div>

    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <div class="text-left text-2xl md:text-4xl font-light">
          {{ headerText }}
        </div>
      </div>

      <div class="join" role="tablist" aria-label="View mode">
        <button class="btn btn-soft join-item"
                :class="{ 'btn-active': viewMode === 'month' }"
                @click="setMode('month')"
                :aria-pressed="viewMode === 'month'">
          Month
        </button>
        <button class="btn btn-soft join-item"
                :class="{ 'btn-active': viewMode === 'week' }"
                @click="setMode('week')"
                :aria-pressed="viewMode === 'week'">
          Week
        </button>
        <button class="btn btn-soft join-item"
                :class="{ 'btn-active': viewMode === 'day' }"
                @click="setMode('day')"
                :aria-pressed="viewMode === 'day'">
          Day
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div v-if="!loading" class="flex flex-col space-y-4 p-4 card bg-base-100 card-border border-base-300">
      <div class="flex justify-between items-center">
        <div>
          <!-- date range picker -->
          <div>
            <InputCalendar v-model="dateRange"/>
          </div>
        </div>
        <div class="flex flex-col">
        <div class="flex flex-row gap-2.5 items-center">
          <div>
            <label class="input">
              <svg class="h-[1em] opacity-50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <g stroke-linejoin="round" stroke-linecap="round" stroke-width="2.5" fill="none" stroke="currentColor">
                  <circle cx="11" cy="11" r="8"></circle>
                  <path d="m21 21-4.3-4.3"></path>
                </g>
              </svg>
              <input v-model="searchText" type="search" placeholder="Search"/>
            </label>
          </div>

          <!-- sports multi-select -->
          <div>
            <div class="dropdown min-w-70">
              <label tabindex="0" class="input input-bordered w-full justify-between items-center gap-2 cursor-pointer">
                <span :class="selectedSports?.length ? 'text-base-content' : 'text-base-content/60'">
                  {{ selectedSports.length ? sports.filter(sport => selectedSports.includes(sport.id)).map(sport => sport.name).join(', ') : 'Select sports' }}
                </span>
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9l6 6 6-6"/>
                </svg>
              </label>
              <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box w-64 p-2 shadow max-h-60 overflow-auto">
                <li v-for="sport in sports" :key="sport.id">
                  <label class="label cursor-pointer justify-between px-2">
                    <span class="label-text">{{ sport.name }}</span>
                    <input type="checkbox" class="checkbox checkbox-sm" :value="sport.id" v-model="selectedSports"/>
                  </label>
                </li>
              </ul>
            </div>

          </div>

          <div class="w-50">
            <label class="label">
              <input type="checkbox" v-model="globalEvents" class="toggle toggle-primary" />
              {{ globalLabel }}
            </label>
          </div>
        </div>
        </div>
      </div>
      <div class="flex justify-end gap-2 items-center">
        <div class="join">
          <button v-if="viewMode === 'day'" class="btn btn-xs md:btn-sm join-item" @click="setMode('week')">Back to week</button>
          <button v-if="viewMode === 'week' || viewMode === 'day' " class="btn btn-xs md:btn-sm join-item" @click="setMode('month')">Back to month</button>
        </div>
        <!-- Prev / Next  -->
        <button class="btn btn-accent btn-circle btn-sm md:btn-md"
                @click="prev"
                :aria-label="viewMode==='month' ? 'Previous month' : (viewMode==='week' ? 'Previous week' : 'Previous day')">
          <svg class="size-5 md:size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
        <button class="btn btn-accent btn-circle btn-sm md:btn-md"
                @click="next"
                :aria-label="viewMode==='month' ? 'Next month' : (viewMode==='week' ? 'Next week' : 'Next day')">
          <svg class="size-5 md:size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      </div>

      <!-- Filtered List View -->
      <template v-if="isFilterActive">
        <div class="flex items-center justify-between">
          <span class="badge badge-ghost">{{ filteredEvents.length }} match(es)</span>
          <button class="btn btn-sm" @click="searchText=''; selectedSports=[]; dateRange=null">
            Clear filters & show calendar
          </button>
        </div>

        <div v-if="filteredEvents.length === 0" class="mt-6 text-base-content/70">
          No events match your filters.
        </div>

        <div v-else class="mt-2 space-y-6">
          <section v-for="g in listGrouped" :key="g.key" class="space-y-2">
            <header class="sticky top-0 z-10 bg-base-100/75 backdrop-blur border-b border-base-200 py-2">
              <h3 class="font-semibold">{{ fmtDateLabel(g.key) }}</h3>
            </header>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
              <article v-for="row in g.items" :key="row.ev.id"
                       class="card card-bordered bg-base-100 hover:shadow-md transition cursor-pointer" @click="openEvent(row.ev)">
                <div class="card-body p-3">
                  <div class="flex items-center justify-between gap-3">
                    <h4 class="card-title text-sm leading-tight truncate">{{ row.ev.title }}</h4>
                    <span class="badge badge-ghost badge-xs">
                      {{
                        row.ev.isAllDay ? 'All day' : (row.ev.mStart?.format?.('HH:mm') + (row.ev.mEnd ? ' - ' + row.ev.mEnd.format('HH:mm') : ''))
                      }}
                    </span>
                  </div>
                  <div class="mt-1 flex flex-wrap gap-2">
                    <span class="badge badge-outline badge-xs">{{ row.ev.category || 'Event' }}</span>
                  </div>
                </div>
              </article>
            </div>
          </section>
        </div>
      </template>

      <!-- Calendar -->
      <template v-else>
        <!-- Month -->
        <template v-if="viewMode === 'month'">
          <div class="overflow-x-auto -mx-2 md:mx-0">
            <!-- weekday headers -->
            <div
                class="grid grid-cols-7 gap-1 md:gap-2 px-2 mb-3 text-xs md:text-sm font-medium text-base-content/70 min-w-[700px] md:min-w-0">
              <div v-for="(wlabel, i) in weekdayLabels" :key="i" class="text-center py-2">
                <span class="md:hidden">{{ wlabel.charAt(0) }}</span>
                <span class="hidden md:inline">{{ wlabel }}</span>
              </div>
            </div>

            <!-- days -->
            <div class="grid grid-cols-7 gap-1 md:gap-2 px-2 min-w-[700px] md:min-w-0">
              <div
                  v-for="day in daysGrid"
                  :key="day.key"
                  class="card card-bordered bg-base-300 hover:shadow-md hover-border transition shadow-sm h-24 md:h-32 text-left relative cursor-pointer"
                  :class="{ 'opacity-50': !day.isCurrentMonth, 'ring-2 ring-primary': day.isToday }"
                  @click="!isFilterActive && (selectedDayKey = day.key, setMode('week'))"
              >
                <div class="absolute right-2 top-2 text-xs md:text-sm font-medium"
                     :class="day.isToday ? 'text-primary' : 'text-base-content/70'">
                  {{ day.date.getDate() }}
                </div>

                <div class="card-body p-2 md:p-3 gap-1">
                  <div class="flex flex-col gap-1">
                    <button
                        v-for="event in (eventsByDay.get(day.key) || []).slice(0, 3)"
                        :key="event.id"
                        :title="event.title"
                        class="flex items-start gap-2 rounded-lg px-2 py-1 text-left w-34 cursor-pointer"
                        :class="statusToneClass(event.status)"
                        @click.stop="openEvent(event)"
                    >
                      <div class="min-w-0 flex-1">
                        <div class="text-[13px] md:text-sm leading-snug truncate">{{ event.title }}</div>
                        <div class="mt-0.5 flex flex-wrap gap-1">
                          <span v-if="timeLabel(event)" class="badge badge-ghost badge-xs bg-base-100">
                            {{ timeLabel(event) }}
                          </span>
                        </div>
                      </div>
                    </button>

                    <span v-if="(eventsByDay.get(day.key)?.length || 0) > 3" class="badge badge-ghost badge-xs w-fit">
                      +{{ (eventsByDay.get(day.key).length - 3) }} more
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>

        <!-- Week -->
        <template v-else-if="viewMode === 'week'">
          <div class="overflow-x-auto -mx-2 md:mx-0">
            <!-- header days -->
            <div class="grid grid-cols-[64px_repeat(7,minmax(120px,1fr))] min-w-[980px] px-2">
              <div></div>
              <div
                  v-for="(day,i) in weekDaysGrid"
                  :key="i"
                  class="px-2 py-3 text-center font-medium select-none"
              >
                <button
                    class="w-full rounded-lg p-2 transition-colors cursor-pointer
           hover:bg-base-200/70 focus-visible:outline-none
           focus-visible:ring-2 focus-visible:ring-base-300"
                    :class="{
      'bg-info/10 text-info ring-1 ring-info/40': selectedDayKey === day.key
    }"
                    :title="`Open ${day.name} ${day.day}`"
                    :aria-label="`Open ${day.name} ${day.day}`"
                    :aria-current="selectedDayKey === day.key ? 'date' : undefined"
                    @click="selectedDayKey = day.key; setMode('day')"
                >
                  <div class="text-xs md:text-sm text-base-content/70 group-hover:text-base-content/90">
                    {{ day.name.toUpperCase() }}
                  </div>
                  <div class="text-lg md:text-xl leading-none">
                    {{ day.day }}
                  </div>
                </button>

                <!-- All-day badges under the pill -->
                <div class="mt-1 flex flex-wrap gap-1 justify-center">
    <span
        v-for="event in dayBlocks(day.key).allDay"
        :key="event.id"
        class="badge badge-ghost badge-xs cursor-pointer"
        :title="event.title"
        @click.stop="openEvent(event)"
    >
      {{ event.title }}
    </span>
                </div>
              </div>

            </div>

            <!-- timeline grid -->
            <div class="grid grid-cols-[64px_repeat(7,minmax(120px,1fr))] min-w-[980px] border-t border-base-300">
              <!-- time gutter -->
              <div>
                <div v-for="hour in hours" :key="hour"
                     class="h-16 text-[10px] md:text-xs text-base-content/60 flex items-start justify-end pr-2 pt-5">
                  {{ hour }}
                </div>
              </div>
              <!-- day columns -->
              <div v-for="day in weekDaysGrid" :key="day.key" class="relative border-l border-base-200">
                <div v-for="hour in hours" :key="hour" class="absolute left-0 right-0 border-t border-base-200/60"
                     :style="{ top: `calc(${Number(hour.slice(0,2)) / 24 * 100}% )` }"></div>
                <div class="relative" :style="{ height: TIMELINE_PX + 'px' }">
                  <div v-for="block in dayBlocks(day.key).timed" :key="block.ev.id"
                       class="absolute left-0 px-1"
                       :style="{
                         top: block.top + '%',
                         height: block.height + '%',
                         width: (100 / dayBlocks(day.key).columns) + '%',
                         transform: `translateX(${block.col * (100 / dayBlocks(day.key).columns)}%)`
                       }">
                    <button
                        class="w-full h-full text-left rounded-xl shadow-sm border p-2 md:p-3 overflow-hidden hover:shadow-md transition cursor-pointer"
                        :title="block.ev.title"
                        :class="statusToneClass(block.ev.status)"
                        @click.stop="openEvent(block.ev)"
                        >
                      <div class="text-xs font-medium leading-tight truncate">{{ block.ev.title }}</div>
                      <div class="text-[11px] opacity-70">
                        {{ timeLabel(block.ev) }}<span v-if="timeLabelEnd(block.ev)"> – {{
                          timeLabelEnd(block.ev)
                        }}</span>
                      </div>
                    </button>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </template>

        <!-- Day -->
        <template v-else-if="viewMode === 'day'">
          <div class="flex items-center justify-between">
            <h3 class="card-title text-base md:text-lg">
              {{ dayHeaderLabel }}
            </h3>
          </div>

          <!-- all-day -->
          <div class="mt-3 flex flex-wrap gap-1">
            <span v-for="event in dayBlocks(selectedDayKey).allDay" :key="event.id"
                  class="badge badge-ghost badge-sm cursor-pointer"
                  :title="event.title"
                  @click.stop="openEvent(event)">
              {{ event.title }}
            </span>
          </div>

          <!-- timeline -->
          <div class="mt-4 overflow-x-auto">
            <div class="grid grid-cols-[64px_minmax(260px,1fr)] min-w-[540px]">
              <div>
                <div v-for="hour in hours" :key="hour"
                     class="h-16 text-[10px] md:text-xs text-base-content/60 flex items-start justify-end pr-2 pt-1">
                  {{ hour }}
                </div>
              </div>
              <div class="relative border-l border-base-200">
                <div v-for="hour in hours" :key="hour"
                     class="absolute left-0 right-0 border-t border-base-200/60"
                     :style="{ top: `calc(${Number(hour.slice(0,2)) / 24 * 100}% )` }"></div>

                <div class="relative" :style="{ height: TIMELINE_PX + 'px' }">
                  <div v-for="block in dayBlocks(selectedDayKey).timed"
                       :key="block.ev.id"
                       class="absolute left-0 px-1"
                       :style="{
                         top: block.top + '%',
                         height: block.height + '%',
                         width: (100 / dayBlocks(selectedDayKey).columns) + '%',
                         transform: `translateX(${block.col * (100 / dayBlocks(selectedDayKey).columns)}%)`
                       }">
                    <button
                        class="w-full h-full text-left rounded-xl shadow-sm border p-3 overflow-hidden hover:shadow-md transition cursor-pointer"
                        :class="statusToneClass(block.ev.status)"
                        :title="block.ev.title"
                        @click.stop="openEvent(block.ev)"
                    >
                      <div class="text-xs font-medium leading-tight truncate">{{ block.ev.title }}</div>
                      <div class="text-[11px] opacity-70">
                        {{ timeLabel(block.ev) }}<span v-if="timeLabelEnd(block.ev)"> – {{
                          timeLabelEnd(block.ev)
                        }}</span>
                      </div>
                    </button>
                  </div>
                </div>

              </div>
            </div>
          </div>
        </template>
      </template>
    </div>
  </div>
  <dialog
      class="modal"
      :open="showEvent"
      @close="closeEvent"
      @click.self="closeEvent"
      aria-labelledby="ev-title"
  >
    <!-- Flex column container with capped height -->
    <div class="modal-box max-w-3xl p-0 rounded-2xl border border-base-300 overflow-hidden flex flex-col max-h-[90vh]">

      <!-- Banner -->
      <header class="relative h-52 md:h-64 shrink-0">
        <img
            v-if="bannerUrl(selectedEvent) && !imgError"
            :src="bannerUrl(selectedEvent)"
            alt=""
            class="absolute inset-0 w-full h-full object-cover"
            @error="imgError = true"
        />
        <div v-else class="absolute inset-0 grid place-items-center bg-gradient-to-br from-base-200 to-base-300">
          <div class="flex items-center gap-3">
            <div class="min-w-0">
              <p class=" uppercase text-l tracking-wide text-base-content/60">
                {{ selectedEvent?.category || selectedEvent?.sport?.name || 'Event' }}
              </p>
              <p class="font-medium text-3xl truncate max-w-[60vw]">
                {{ selectedEvent?.title || 'Untitled event' }}
              </p>
            </div>
          </div>
        </div>
        <!-- soft gradient so text below reads well -->
        <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-black/10 to-transparent pointer-events-none"></div>

        <!-- Close -->
        <button
            class="absolute right-3 top-3 btn btn-circle btn-ghost btn-sm text-base-content/70 hover:text-base-content hover:bg-base-100/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-info"
            @click="closeEvent"
            aria-label="Close"
        >
          ✕
        </button>
      </header>

      <!-- Sticky title bar  -->
      <div class="sticky top-0 z-10 bg-base-100/90 backdrop-blur supports-[backdrop-filter]:bg-base-100/70 border-b border-base-300">
        <div class="px-6 pt-4 pb-4">
          <h3 id="ev-title" class="text-2xl md:text-3xl font-semibold leading-tight break-words">
            {{ selectedEvent?.title || 'Event' }}
          </h3>
          <p v-if="teamSides(selectedEvent).homeName || teamSides(selectedEvent).awayName"
             class="mt-2 text-sm text-base-content/70 truncate">
            <span class="font-medium">{{ teamSides(selectedEvent).homeName || '-' }}</span>
            <span class="px-2 text-base-content/50">vs</span>
            <span class="font-medium">{{ teamSides(selectedEvent).awayName || '-' }}</span>
          </p>
          <!-- Status / Tags row -->
          <div class="flex flex-wrap gap-2 pt-2">
          <span
              v-if="selectedEvent?.status"
              class="badge badge-lg"
              :class="statusToneClass(selectedEvent?.status)"
              :title="selectedEvent?.status"
          >
            {{ selectedEvent?.status }}
          </span>

            <!-- sport -->
            <span
                v-if="selectedEvent?.sport?.name || selectedEvent?.category"
                class="badge badge-lg badge-outline border-base-300 whitespace-nowrap"
                :title="selectedEvent?.category || selectedEvent?.sport?.name"
            >
            <span class="max-w-[14rem] truncate inline-block align-middle">
              {{ selectedEvent?.category || selectedEvent?.sport?.name }}
            </span>
          </span>

            <!-- competition name -->
            <span
                v-if="selectedEvent?.competition?.name"
                class="badge badge-lg badge-outline border-base-300"
                :title="selectedEvent?.competition?.name"
            >
            <span class="max-w-[14rem] truncate inline-block align-middle">
              {{ selectedEvent?.competition?.name }}
            </span>
          </span>

            <!-- competition type -->
            <span
                v-if="selectedEvent?.competition?.type"
                class="badge badge-lg badge-ghost"
                :title="compTypeLabel(selectedEvent?.competition?.type)"
            >
            <span class="max-w-[12rem] truncate inline-block align-middle">
              {{ compTypeLabel(selectedEvent?.competition?.type) }}
            </span>
          </span>
          </div>
        </div>

      </div>

      <!-- Scrollable body -->
      <div class="flex-1 overflow-y-auto">
        <section class="px-6 pt-4 pb-6 space-y-6">

          <!-- Info tiles -->
          <div class="grid sm:grid-cols-3 gap-2">
            <div class="rounded-xl border-2 border-base-300 hover:border-info/70 transition-colors p-3">
              <div class="text-[11px] uppercase tracking-wide text-base-content/60 mb-1">When</div>
              <div class="text-sm">
                {{ selectedEvent?.isAllDay ? 'All day' : eventTimeRange(selectedEvent) }}
                <span v-if="eventDuration(selectedEvent)" class="text-base-content/60"> • {{ eventDuration(selectedEvent) }}</span>
              </div>
            </div>
            <div class="rounded-xl border-2 border-base-300 hover:border-info/70 transition-colors p-3">
              <div class="text-[11px] uppercase tracking-wide text-base-content/60 mb-1">Status</div>
              <div class="text-sm">
              <span v-if="selectedEvent?.status" class="badge" :class="statusToneClass(selectedEvent?.status)">
                {{ selectedEvent?.status }}
              </span>
                <span v-else class="text-base-content/60">—</span>
              </div>
            </div>
            <div class="rounded-xl border-2 border-base-300 hover:border-info/70 transition-colors p-3">
              <div class="text-[11px] uppercase tracking-wide text-base-content/60 mb-1">Tags</div>
              <div class="flex flex-wrap gap-2">
              <span
                  v-if="selectedEvent?.sport?.name || selectedEvent?.category"
                  class="badge badge-outline border-base-300"
                  :title="selectedEvent?.category || selectedEvent?.sport?.name"
              ><span class="max-w-[12rem] truncate inline-block align-middle">{{ selectedEvent?.category || selectedEvent?.sport?.name }}</span></span>

                <span
                    v-if="selectedEvent?.competition?.name"
                    class="badge badge-outline border-base-300"
                    :title="selectedEvent?.competition?.name"
                ><span class="max-w-[12rem] truncate inline-block align-middle">{{ selectedEvent?.competition?.name }}</span></span>

                <span
                    v-if="selectedEvent?.competition?.type"
                    class="badge badge-ghost"
                    :title="compTypeLabel(selectedEvent?.competition?.type)"
                ><span class="max-w-[10rem] truncate inline-block align-middle">{{ compTypeLabel(selectedEvent?.competition?.type) }}</span></span>
              </div>
            </div>
          </div>

          <!-- Matchup -->
          <div class="rounded-2xl border-2 border-base-300 hover:border-info/70 transition-colors">
            <div class="px-4 py-3 border-b border-base-300 text-xs font-medium uppercase tracking-wide text-base-content/60">
              Matchup
            </div>
            <div class="px-4 py-5">
              <template v-if="(selectedEvent?.teams?.length || 0) > 0">
                <div class="grid md:grid-cols-[1fr_auto_1fr] items-center gap-4">
                  <div class="rounded-xl border border-base-300 hover:border-info/70 transition-colors px-3 py-2">
                    <div class="text-[10px] uppercase tracking-wide text-base-content/60">Home</div>
                    <div class="text-base md:text-lg font-medium leading-tight truncate"
                         :title="teamSides(selectedEvent).homeName">
                      {{ teamSides(selectedEvent).homeName || '-' }}
                    </div>
                  </div>
                  <div class="text-center">
                    <div class="text-[10px] uppercase tracking-wide text-base-content/60">vs</div>
                    <div v-if="selectedEvent?.teams?.some(t=>t.score!==null)" class="mt-1 text-2xl font-semibold">
                      {{ (selectedEvent?.teams?.find(team=>team.side==='home')?.score) ?? '-' }}
                      <span class="px-1 text-base-content/60">:</span>
                      {{ (selectedEvent?.teams?.find(team=>team.side==='away')?.score) ?? '-' }}
                    </div>
                  </div>
                  <div class="rounded-xl border border-base-300 hover:border-info/70 transition-colors px-3 py-2 md:text-right">
                    <div class="text-[10px] uppercase tracking-wide text-base-content/60">Away</div>
                    <div class="text-base md:text-lg font-medium leading-tight truncate"
                         :title="teamSides(selectedEvent).awayName">
                      {{ teamSides(selectedEvent).awayName || '-' }}
                    </div>
                  </div>
                </div>

                <div v-if="teamSides(selectedEvent).others.length" class="mt-3 flex flex-wrap gap-2">
                <span v-for="team in teamSides(selectedEvent).others" :key="team.teamId" class="badge badge-outline border-base-300">
                  <span class="max-w-[12rem] truncate inline-block align-middle" :title="team?.team?.name || 'Team'">
                    {{ team?.team?.name || 'Team' }}
                  </span>
                </span>
                </div>
              </template>
              <template v-else>
                <span class="text-base-content/60">No teams listed.</span>
              </template>
            </div>
          </div>

          <!-- Venue & Competition -->
          <div class="grid md:grid-cols-2 gap-4">
            <div class="rounded-2xl border-2 border-base-300 hover:border-info/70 transition-colors">
              <div class="px-4 py-3 border-b border-base-300 text-xs font-medium uppercase tracking-wide text-base-content/60">
                Venue
              </div>
              <div class="px-4 py-3 text-sm">
                <template v-if="selectedEvent?.venue">
                  <div class="font-medium">{{ selectedEvent.venue.name }}</div>
                  <div class="mt-1 text-base-content/70 leading-6">
                    {{ selectedEvent.venue.addressLine1 }}
                    <template v-if="selectedEvent.venue.addressLine2">, {{ selectedEvent.venue.addressLine2 }}</template><br>
                    {{ selectedEvent.venue.postalCode }} {{ selectedEvent.venue.city }}, {{ selectedEvent.venue.country }}
                  </div>
                  <div class="mt-2 flex flex-wrap gap-2">
                    <span class="badge badge-ghost">{{ selectedEvent.venue.timeZone || timeZone }}</span>
                    <span class="badge badge-ghost">{{ selectedEvent.venue.isIndoor ? 'Indoor' : 'Outdoor' }}</span>
                  </div>
                </template>
                <template v-else>
                  <span class="text-base-content/60">No venue info.</span>
                </template>
              </div>
            </div>

            <div class="rounded-2xl border-2 border-base-300 hover:border-info/70 transition-colors">
              <div class="px-4 py-3 border-b border-base-300 text-xs font-medium uppercase tracking-wide text-base-content/60">
                Competition
              </div>
              <div class="px-4 py-3 text-sm space-y-2">
                <div class="grid grid-cols-3 gap-3">
                  <div class="text-base-content/60">Name</div>
                  <div class="col-span-2 min-w-0 truncate">{{ selectedEvent?.competition?.name || '-' }}</div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                  <div class="text-base-content/60">Type</div>
                  <div class="col-span-2">{{ compTypeLabel(selectedEvent?.competition?.type) || '-' }}</div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                  <div class="text-base-content/60">Sport</div>
                  <div class="col-span-2">{{ selectedEvent?.sport?.name || selectedEvent?.category || '-' }}</div>
                </div>
                <div v-if="selectedEvent?.created_by?.name || selectedEvent?.createdBy?.name" class="grid grid-cols-3 gap-3">
                  <div class="text-base-content/60">Created by</div>
                  <div class="col-span-2 min-w-0 truncate">
                    {{ selectedEvent?.created_by?.name || selectedEvent?.createdBy?.name }}
                    <p class="text-base-content/60" v-if="selectedEvent?.created_by?.email || selectedEvent?.createdBy?.email">
                    {{ selectedEvent?.created_by?.email || selectedEvent?.createdBy?.email }}
                  </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Description -->
          <div v-if="selectedEvent?.description" class="rounded-2xl border-2 border-base-300 hover:border-info/70 transition-colors">
            <div class="px-4 py-3 border-b border-base-300 text-xs font-medium uppercase tracking-wide text-base-content/60">
              Description
            </div>
            <p class="px-4 py-3 text-sm whitespace-pre-line">
              {{ selectedEvent.description }}
            </p>
          </div>
        </section>
      </div>

      <!-- Footer -->
      <footer class="px-6 py-4 border-t border-base-300 flex items-center justify-end gap-2 shrink-0">
        <button
            class="btn btn-ghost hover:bg-base-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-info"
            @click="closeEvent"
        >
          Close
        </button>
      </footer>
    </div>

  </dialog>

</template>

<style scoped>
.card.opacity-50::after {
  content: "";
  position: absolute;
  inset: 0;
  background-image: linear-gradient(
      45deg,
      rgba(0, 0, 0, .03) 25%,
      transparent 25%,
      transparent 50%,
      rgba(0, 0, 0, .03) 50%,
      rgba(0, 0, 0, .03) 75%,
      transparent 75%,
      transparent
  );
  background-size: 16px 16px;
  pointer-events: none;
}

.hover-border {
  transition: border-color .15s ease, box-shadow .15s ease, outline-color .15s ease;
}

.hover-border{
  border-width: 2px;
  border-color: transparent;
  transition: border-color .12s ease;
}
.hover-border:hover,
.hover-border:focus-visible {
  border-color: var(--color-info);
}
</style>
