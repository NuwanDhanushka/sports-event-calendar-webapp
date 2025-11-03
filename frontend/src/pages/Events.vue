<script setup>
import {computed, ref} from "vue";
import InputCalendar from "../components/InputCalendar.vue";
import moment from "moment-timezone";

const timeZone = "Europe/Vienna";

// today/view
const today = moment.tz(timeZone);
const viewYear = ref(today.year());
const viewMonth = ref(today.month()); // 0–11

const viewMode = ref('month');        // 'month' | 'week' | 'day'
const selectedDayKey = ref('');       // 'YYYY-MM-DD'

// selection states
const selectedSports = ref([]);       // ['Run', 'Football']
const dateRange = ref(null);          // "YYYY-MM-DD/YYYY-MM-DD" or {start,end}

const HOUR_PX = 64;                // for time line height - h-16 (16*4 = 64px)
const TIMELINE_PX = HOUR_PX * 24;  // 1536px

const monthTitle = computed(() =>
    moment.tz({year: viewYear.value, month: viewMonth.value, date: 1}, timeZone).format('MMMM YYYY')
);

const selectedKeyOrFirst = computed(() =>
    selectedDayKey.value ||
    moment.tz({year: viewYear.value, month: viewMonth.value, date: 1}, timeZone).format('YYYY-MM-DD')
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

const headerText = computed(() => {
  if (viewMode.value === 'month') return monthTitle.value;
  if (viewMode.value === 'week') return weekTitle.value;
  return dayTitle.value;
});

//Mock date for testing
const MOCK_EVENTS = [
  {
    id: 101,
    title: "Vienna City Marathon Expo",
    start_at: "2025-11-01T10:00:00+01:00",
    end_at: "2025-11-01T18:00:00+01:00",
    category: "Run",
    status: "confirmed",
    color: "accent"
  },
  {
    id: 102,
    title: "Regional Football Finals",
    date: "2025-11-12",
    all_day: true,
    category: "Football",
    status: "tentative"
  },
  {
    id: 103,
    title: "Basketball Cup",
    start_at: "2025-11-20T18:00:00+01:00",
    end_at: "2025-11-20T21:00:00+01:00",
    category: "Basketball",
    status: "confirmed"
  },
];

//normalize event data for the view
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

  // timed event
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

const rawEvents = ref(MOCK_EVENTS);
const normEvents = computed(() => rawEvents.value.map(normalizeEvent));

//Filters
const searchText = ref('');

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

function titleMatches(ev) {
  const q = searchText.value.trim().toLowerCase();
  return !q || String(ev.title || '').toLowerCase().includes(q);
}

function sportMatches(ev) {
  if (!selectedSports.value?.length) return true;
  return selectedSports.value.includes((ev.category || '').trim());
}

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

const isFilterActive = computed(() =>
    !!searchText.value.trim() ||
    (selectedSports.value?.length || 0) > 0 ||
    !!dateFrom.value || !!dateTo.value
);

const filteredEvents = computed(() =>
    normEvents.value.filter(ev => titleMatches(ev) && sportMatches(ev) && inDateRange(ev))
);


const eventsByDay = computed(() => {
  const map = new Map();
  const source = isFilterActive.value ? filteredEvents.value : normEvents.value;

  for (const ev of source) {
    const startDay = (ev.mStart ?? moment.tz(ev.date, timeZone)).clone().startOf("day");
    const endDay = (ev.mEnd ?? startDay).clone().startOf("day");
    const cursor = startDay.clone();

    while (cursor.isSameOrBefore(endDay, "day")) {
      const key = cursor.format("YYYY-MM-DD");
      if (!map.has(key)) map.set(key, []);
      map.get(key).push(ev);
      cursor.add(1, "day");
    }
  }

  // sort per day
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

// list view data
const listSorted = computed(() => {
  const items = filteredEvents.value.map(ev => ({
    key: ev.keyStart || ev.mStart?.format?.('YYYY-MM-DD') || ev.date || '',
    t: ev.isAllDay ? '00:00' : (ev.mStart?.format?.('HH:mm') || '00:00'),
    ev
  }));
  items.sort((a, b) => a.key === b.key ? a.t.localeCompare(b.t) : a.key.localeCompare(b.key));
  return items;
});
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

function fmtDateLabel(key) {
  return key ? moment.tz(key, 'YYYY-MM-DD', timeZone).format('dddd, D MMMM YYYY') : '';
}

//colors for statues
function statusToneClass(status) {
  switch ((status || "").toLowerCase()) {
    case "confirmed":
      return "bg-success/70 hover:bg-success/70 border border-success text-success-content";
    case "tentative":
      return "bg-warning/70 hover:bg-warning/70 border border-warning text-warning-content";
    case "cancelled":
    case "canceled":
      return "bg-error/70 hover:bg-error/70 border border-error text-error-content";
    default:
      return "bg-base-100/70 hover:bg-base-200 border border-base-300";
  }
}

function timeLabel(ev) {
  if (ev?.isAllDay) return "All day";
  if (!ev?.mStart) return "";
  const start = ev.mStart.format("HH:mm");
  const end = ev.mEnd ? ev.mEnd.format("HH:mm") : "";
  return end ? `${start}–${end}` : start;
}

function timeLabelEnd(ev) {
  if (ev?.isAllDay || !ev?.mEnd) return "";
  const mEnd = ev.mEnd;
  return mEnd?.isValid?.() ? mEnd.format("HH:mm") : "";
}

const weekdayLabels = computed(() => {
  const start = moment.tz({year: 2021, month: 0, date: 4}, timeZone).startOf("isoWeek");
  return Array.from({length: 7}, (_, i) => start.clone().add(i, "days").format("ddd"));
});

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

function startOfWeekMon(d) {
  return moment.tz(d, timeZone).startOf("isoWeek").toDate();
}

const selectedDate = computed(() => {
  const baseKey =
      selectedDayKey.value ||
      moment.tz({year: viewYear.value, month: viewMonth.value, date: 1}, timeZone).format("YYYY-MM-DD");
  return moment.tz(baseKey, "YYYY-MM-DD", timeZone).startOf("day").toDate();
});

const weekStart = computed(() => startOfWeekMon(selectedDate.value));

const weekDaysGrid = computed(() => {
  const start = moment.tz(weekStart.value, timeZone).startOf("day");
  return Array.from({length: 7}, (_, i) => {
    const m = start.clone().add(i, "days");
    return {key: m.format("YYYY-MM-DD"), date: m.toDate(), name: m.format("ddd"), day: m.date()};
  });
});

const hours = Array.from({length: 24}, (_, h) => moment({hour: h, minute: 0}).format("HH:mm"));

const dayHeaderLabel = computed(() => {
  const key =
      selectedDayKey.value ||
      moment.tz({year: viewYear.value, month: viewMonth.value, date: 1}, timeZone).format('YYYY-MM-DD');
  return moment.tz(key, 'YYYY-MM-DD', timeZone).format('dddd');
});

function dayBlocks(dayKey) {
  const list = eventsByDay.value.get(dayKey) || [];

  const allDay = list.filter(e => e.isAllDay);
  const timed = [];

  const dayStart = moment.tz(dayKey, "YYYY-MM-DD", timeZone).startOf("day");
  const dayEnd = dayStart.clone().endOf("day");

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

function ensureSelectedDay() {
  if (!selectedDayKey.value) {
    selectedDayKey.value = moment
        .tz({year: viewYear.value, month: viewMonth.value, date: 1}, timeZone)
        .format('YYYY-MM-DD');
  }
}

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

function setMode(mode) {
  if ((mode === 'week' || mode === 'day') && !selectedDayKey.value) {
    selectedDayKey.value = moment
        .tz({year: viewYear.value, month: viewMonth.value, date: 1}, timeZone)
        .format('YYYY-MM-DD');
  }
  viewMode.value = mode;
}

const options = ["Run", "Football", "Basketball", "Tennis"];
const label = computed(() =>
    selectedSports.value.length ? selectedSports.value.join(", ") : "Select sports"
);
</script>

<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <div class="text-left text-2xl md:text-4xl font-light">
          {{ headerText }}
        </div>

        <!-- Prev / Next that adapt to view -->
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
    <div class="flex flex-col space-y-4 p-4 card bg-base-100 card-border border-base-300">
      <div class="flex flex-row gap-2.5">
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
                {{ label }}
              </span>
              <svg xmlns="http://www.w3.org/2000/svg" class="size-4 opacity-60" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9l6 6 6-6"/>
              </svg>
            </label>
            <ul tabindex="0"
                class="dropdown-content menu bg-base-100 rounded-box w-64 p-2 shadow max-h-60 overflow-auto">
              <li v-for="o in options" :key="o">
                <label class="label cursor-pointer justify-between px-2">
                  <span class="label-text">{{ o }}</span>
                  <input type="checkbox" class="checkbox checkbox-sm" :value="o" v-model="selectedSports"/>
                </label>
              </li>
            </ul>
          </div>
        </div>

        <!-- date range picker -->
        <div>
          <InputCalendar v-model="dateRange"/>
        </div>

      </div>

      <!-- Filtered LIST VIEW -->
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
                       class="card card-bordered bg-base-100 hover:shadow-md transition">
                <div class="card-body p-3">
                  <div class="flex items-center justify-between gap-3">
                    <h4 class="card-title text-sm leading-tight truncate">{{ row.ev.title }}</h4>
                    <span class="badge badge-ghost badge-xs">
                      {{
                        row.ev.isAllDay ? 'All day' : (row.ev.mStart?.format?.('HH:mm') + (row.ev.mEnd ? ' – ' + row.ev.mEnd.format('HH:mm') : ''))
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
                  class="card card-bordered bg-base-300 hover:shadow-md transition shadow-sm h-24 md:h-32 text-left relative cursor-pointer"
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
                        class="flex items-start gap-2 rounded-lg px-2 py-1 text-left w-34"
                        :class="statusToneClass(event.status)"
                        @click.stop
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
        class="badge badge-ghost badge-xs"
        :title="event.title"
        @click.stop
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
                        class="w-full h-full text-left rounded-xl shadow-sm border p-2 md:p-3 overflow-hidden hover:shadow-md transition"
                        :title="block.ev.title"
                        :class="statusToneClass(block.ev.status)"
                        @click.stop>
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
            <div class="join">
              <button class="btn btn-xs md:btn-sm join-item" @click="setMode('week')">Back to week</button>
              <button class="btn btn-xs md:btn-sm join-item" @click="setMode('month')">Back to month</button>
            </div>
          </div>

          <!-- all-day -->
          <div class="mt-3 flex flex-wrap gap-1">
            <span v-for="event in dayBlocks(selectedDayKey).allDay" :key="event.id"
                  class="badge badge-ghost badge-sm"
                  :title="event.title"
                  @click.stop>
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
                        class="w-full h-full text-left rounded-xl shadow-sm border p-3 overflow-hidden hover:shadow-md transition"
                        :class="statusToneClass(block.ev.status)"
                        :title="block.ev.title"
                        @click.stop
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
</style>
