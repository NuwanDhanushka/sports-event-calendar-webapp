<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import moment from 'moment-timezone';

import { listSports } from '../api/sports.js';
import { listCompetitions, listCompetitionTeams } from '../api/competitions.js';
import { createEvent } from '../api/events.js';
import { listVenues } from '../api/venues.js';

const timeZone = 'Europe/Vienna';

const loadingSports = ref(false);
const loadingMeta = ref(false);
const loadingVenues = ref(false);
const submitting = ref(false);

const sports = ref([]);
const competitions = ref([]);
const teams = ref([]);
const venues = ref([]);

const toast = ref({
  visible: false,
  type: 'success', // 'success' | 'error'
  message: '',
});

/**
 * Toast to show success or error message
 * @param type
 * @param message
 */
function showToast(type, message) {
  toast.value.type = type;
  toast.value.message = message;
  toast.value.visible = true;

  // auto-hide after 3s
  setTimeout(() => {
    toast.value.visible = false;
  }, 3000);
}

const form = ref({
  title: '',
  description: '',
  bannerFile: null,
  bannerPreview: '',
  status: 'confirmed',
  sportId: '',
  competitionId: '',
  venueId: '',
  homeTeamId: '',
  awayTeamId: '',
  startDate: '',
  startTime: '',
  endDate: '',
  endTime: '',
});

/** Status options */
const statusOptions = [
  { value: 'confirmed', label: 'Confirmed' },
  { value: 'scheduled', label: 'Scheduled' },
  { value: 'tentative', label: 'Tentative' },
  { value: 'cancelled', label: 'Cancelled' },
];

/** Check if form is valid */
const canSubmit = computed(() => {
  if (submitting.value) return false;
  if (!form.value.title.trim()) return false;
  if (!form.value.sportId) return false;
  if (!form.value.venueId) return false;
  if (!form.value.startDate || !form.value.startTime) return false;
  if (!form.value.endDate || !form.value.endTime) return false;
  if (!form.value.homeTeamId || !form.value.awayTeamId) return false;
  if (form.value.homeTeamId === form.value.awayTeamId) return false;
  return true
});

/**
 * Get sports from API
 * @returns {Promise<void>}
 */
async function fetchSports() {
  try {
    loadingSports.value = true;
    const res = await listSports({ all: 1, team_only: 1 });
    const payload = res?.data ?? res;
    sports.value = payload?.data?.sports || payload?.sports || [];
  } catch (e) {
    console.error(e);
  } finally {
    loadingSports.value = false;
  }
}

/**
 * Get venues from API
 * @returns {Promise<void>}
 */
async function fetchVenues() {
  try {
    loadingVenues.value = true;
    const res = await listVenues({ all: 1 });
    const payload = res?.data ?? res;
    venues.value = payload?.data?.venues || payload?.venues || [];
  } catch (e) {
    console.error(e);
  } finally {
    loadingVenues.value = false;
  }
}

/**
 * Get competitions for sport from API
 * @param sportId
 * @returns {Promise<void>}
 */
async function fetchCompetitionsForSport(sportId) {
  competitions.value = [];
  teams.value = [];
  form.value.competitionId = '';
  form.value.homeTeamId = '';
  form.value.awayTeamId = '';

  if (!sportId) return;

  loadingMeta.value = true;
  try {
    const res = await listCompetitions({ sport_id: sportId, all: 1 });
    const payload = res?.data ?? res;
    competitions.value =
        payload?.data?.competitions || payload?.competitions || [];
  } catch (e) {
    console.error(e);
  } finally {
    loadingMeta.value = false;
  }
}

/**
 * Get teams for competition from API
 * @param competitionId
 * @returns {Promise<void>}
 */
async function fetchTeamsForCompetition(competitionId) {
  teams.value = [];
  form.value.homeTeamId = '';
  form.value.awayTeamId = '';

  if (!competitionId) return;

  loadingMeta.value = true;

  try {
    const res = await listCompetitionTeams(competitionId);
    const payload = res?.data ?? res;
    teams.value = payload?.data?.teams || payload?.teams || [];
  } catch (e) {
    console.error(e);
  } finally {
    loadingMeta.value = false;
  }
}

/**
 * Watch for sport change
 */
watch(
    () => form.value.sportId,
    (sportId) => {
      fetchCompetitionsForSport(sportId);
    }
)

/**
 * Watch for competition change
 */
watch(
    () => form.value.competitionId,
    (competitionId) => {
      fetchTeamsForCompetition(competitionId);
    }
)

/**
 * When banner changes, update preview
 * @param e
 */
function onBannerChange(e) {
  const file = e?.target?.files?.[0];
  if (!file) {
    form.value.bannerFile = null;
    form.value.bannerPreview = '';
    return;
  }
  form.value.bannerFile = file;
  form.value.bannerPreview = URL.createObjectURL(file);
}

/**
 * Build datetime string from date and time
 * @param date
 * @param time
 * @returns {*|null}
 */
function buildDateTime(date, time) {
  if (!date || !time) return null;
  const m = moment.tz(`${date} ${time}`, 'YYYY-MM-DD HH:mm', timeZone);
  return m.isValid() ? m.format('YYYY-MM-DD HH:mm:ss') : null;
}

/**
 * Reset form to initial state
 */
function resetForm() {
  form.value = {
    title: '',
    description: '',
    bannerFile: null,
    bannerPreview: '',
    status: 'confirmed',
    sportId: '',
    competitionId: '',
    venueId: '',
    homeTeamId: '',
    awayTeamId: '',
    startDate: '',
    startTime: '',
    endDate: '',
    endTime: '',
  };
  competitions.value = [];
  teams.value = [];
}

/**
 * Handle start date change
 * @param event
 */
function onStartDateChange(event) {
  form.value.startDate = event?.target?.value || '';
}

/**
 * Handle end date change
 * @param event
 */
function onEndDateChange(event) {
  form.value.endDate = event?.target?.value || '';
}

async function handleSubmit() {
  if (!canSubmit.value) return;

  submitting.value = true;

  try {

    /** Get the data and time and build the datetime string */
    const startAt = buildDateTime(form.value.startDate, form.value.startTime);
    const endAt = buildDateTime(form.value.endDate, form.value.endTime);

    const payload = {
      title: form.value.title.trim(),
      description: form.value.description?.trim() || null,
      status: form.value.status || 'confirmed',
      sport_id: Number(form.value.sportId),
      competition_id: form.value.competitionId
          ? Number(form.value.competitionId)
          : null,
      venue_id: form.value.venueId ? Number(form.value.venueId) : null,
      start_at: startAt,
      end_at: endAt,
      banner: form.value.bannerFile || null,
      teams: [
        { team_id: Number(form.value.homeTeamId), side: 'home' },
        { team_id: Number(form.value.awayTeamId), side: 'away' },
      ],
    }

    const res = await createEvent(payload);
    const created = res?.data ?? res;

    showToast('success', 'Event created successfully.')
    resetForm();

  } catch (e) {
    console.error(e);
    const message =
        e?.data?.message ||
        e?.response?.data?.message ||
        e?.message ||
        'Failed to create event.'

    showToast('error', message);
  } finally {
    submitting.value = false;
  }
}

onMounted(() => {
  fetchSports();
  fetchVenues();
})
</script>

<template>
  <div
      v-if="toast.visible"
      class="toast toast-end z-50"
  >
    <div
        class="alert"
        :class="toast.type === 'success' ? 'alert-success' : 'alert-error'"
    >
      <span>{{ toast.message }}</span>
    </div>
  </div>
  <div class="max-w-5xl mx-auto px-3 md:px-4 py-4 md:py-6 space-y-4 md:space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
      <div>
        <h1 class="text-xl md:text-3xl font-semibold leading-tight">
          Add new event
        </h1>
        <p class="mt-1 text-xs md:text-sm text-base-content/60">
          Create a match or event with banner, schedule, competition & teams.
        </p>
      </div>
    </div>

    <!-- Form -->
    <div class="card bg-base-100 border border-base-300">
      <form class="card-body space-y-5 md:space-y-6" @submit.prevent="handleSubmit">
        <!-- Banner -->
        <div class="grid gap-3 md:gap-4 md:grid-cols-[minmax(0,220px),1fr] items-stretch">
          <div class="space-y-1.5">
            <label class="label py-1">
              <span class="label-text font-medium text-xs md:text-sm">Banner</span>
              <span class="label-text-alt text-[10px] text-base-content/60">Optional</span>
            </label>
            <input
                type="file"
                accept="image/png, image/jpg, image/webp"
                class="file-input file-input-bordered file-input-xs md:file-input-sm w-full"
                @change="onBannerChange"
            />
            <p class="text-[10px] text-base-content/60 leading-snug">
              Recommended 1200×400px or similar wide ratio.
            </p>
          </div>
          <div
              class="relative h-24 md:h-32 rounded-xl border border-dashed border-base-300 overflow-hidden bg-base-200/40 flex items-center justify-center"
          >
            <img
                v-if="form.bannerPreview"
                :src="form.bannerPreview"
                alt="Banner preview"
                class="w-full h-full object-cover"
            />
            <div
                v-else
                class="flex flex-col items-center justify-center gap-1 text-[10px] md:text-xs text-base-content/60"
            >
              <span>Banner preview appears here</span>
            </div>
          </div>
        </div>

        <!-- Title, Status -->
        <div class="grid gap-3 md:gap-4 md:grid-cols-2">
          <div>
            <label class="label py-1">
              <span class="label-text font-medium text-xs md:text-sm">Title</span>
            </label>
            <input
                v-model="form.title"
                type="text"
                placeholder="Rapid Vienna vs Austria Vienna"
                class="input input-bordered input-sm md:input-md w-full"
                required
            />
          </div>
          <div>
            <label class="label py-1">
              <span class="label-text font-medium text-xs md:text-sm">Status</span>
            </label>
            <select
                v-model="form.status"
                class="select select-bordered select-sm md:select-md w-full"
            >
              <option
                  v-for="s in statusOptions"
                  :key="s.value"
                  :value="s.value"
              >
                {{ s.label }}
              </option>
            </select>
          </div>
        </div>

        <!-- Description -->
        <div>
          <label class="label py-1">
            <span class="label-text font-medium text-xs md:text-sm">Description</span>
            <span class="label-text-alt text-[10px] text-base-content/60">Optional</span>
          </label>
          <textarea
              v-model="form.description"
              class="textarea textarea-bordered textarea-sm md:textarea-md w-full min-h-20 md:min-h-24"
              placeholder="Short notes about the match, venue, special info, etc."
          />
        </div>

        <!-- Time -->
        <div class="grid gap-3 md:gap-4 md:grid-cols-2">
          <!-- Start -->
          <div class="card bg-base-100 border border-base-300 p-3 md:p-4 space-y-2">
            <div class="text-[10px] md:text-[11px] uppercase tracking-wide text-base-content/60">
              Start
            </div>

            <!-- Start date -->
            <label class="label py-1">
              <span class="label-text text-xs md:text-sm">Start date</span>
            </label>
            <button
                type="button"
                popovertarget="start-date-popover"
                class="input input-bordered input-sm md:input-md w-full text-left"
                id="start-date-button"
                style="anchor-name: --start-date-anchor"
            >
              {{ form.startDate || 'Pick start date' }}
            </button>
            <div
                popover
                id="start-date-popover"
                class="dropdown bg-base-100 rounded-box shadow-lg p-2"
                style="position-anchor: --start-date-anchor"
            >
              <calendar-date
                  class="cally"
                  :value="form.startDate"
                  @change="onStartDateChange"
              >
                <calendar-month></calendar-month>
              </calendar-date>
            </div>

            <!-- Start time -->
            <label class="label py-1">
              <span class="label-text text-xs md:text-sm">Start time</span>
            </label>
            <input
                v-model="form.startTime"
                type="time"
                class="input input-bordered input-sm md:input-md w-full"
                required
            />
          </div>

          <!-- End -->
          <div class="card bg-base-100 border border-base-300 p-3 md:p-4 space-y-2">
            <div class="text-[10px] md:text-[11px] uppercase tracking-wide text-base-content/60">
              End
            </div>

            <!-- End date -->
            <label class="label py-1">
              <span class="label-text text-xs md:text-sm">End date</span>
            </label>
            <button
                type="button"
                popovertarget="end-date-popover"
                class="input input-bordered input-sm md:input-md w-full text-left"
                id="end-date-button"
                style="anchor-name: --end-date-anchor"
            >
              {{ form.endDate || 'Pick end date' }}
            </button>
            <div
                popover
                id="end-date-popover"
                class="dropdown bg-base-100 rounded-box shadow-lg p-2"
                style="position-anchor: --end-date-anchor"
            >
              <calendar-date
                  class="cally"
                  :value="form.endDate"
                  @change="onEndDateChange"
              >
                <calendar-month></calendar-month>
              </calendar-date>
            </div>

            <!-- End time -->
            <label class="label py-1">
              <span class="label-text text-xs md:text-sm">End time</span>
            </label>
            <input
                v-model="form.endTime"
                type="time"
                class="input input-bordered input-sm md:input-md w-full"
                required
            />
          </div>
        </div>

        <!-- Sport, Competition, Venue -->
        <div class="grid gap-3 md:gap-4 md:grid-cols-3">
          <div>
            <label class="label py-1">
              <span class="label-text font-medium text-xs md:text-sm">Sport</span>
            </label>
            <select
                v-model="form.sportId"
                class="select select-bordered select-sm md:select-md w-full"
                :disabled="loadingSports"
                required
            >
              <option value="" disabled>Select sport</option>
              <option v-for="s in sports" :key="s.id" :value="s.id">
                {{ s.name }}
              </option>
            </select>
            <p v-if="loadingSports" class="text-[10px] text-base-content/60 mt-1">
              Loading sports...
            </p>
          </div>

          <div>
            <label class="label py-1">
              <span class="label-text font-medium text-xs md:text-sm">Competition</span>
              <span class="label-text-alt text-[10px] text-base-content/60">
                From selected sport
              </span>
            </label>
            <select
                v-model="form.competitionId"
                class="select select-bordered select-sm md:select-md w-full"
                :disabled="!form.sportId || loadingMeta"
            >
              <option value="">No competition</option>
              <option v-for="c in competitions" :key="c.id" :value="c.id">
                {{ c.name }}
              </option>
            </select>
            <p v-if="loadingMeta" class="text-[10px] text-base-content/60 mt-1">
              Loading competitions & teams..
            </p>
          </div>

          <div>
            <label class="label py-1">
              <span class="label-text font-medium text-xs md:text-sm">Venue</span>
            </label>
            <select
                v-model="form.venueId"
                class="select select-bordered select-sm md:select-md w-full"
                :disabled="loadingVenues"
                required
            >
              <option value="" disabled>Select venue</option>
              <option v-for="v in venues" :key="v.id" :value="v.id">
                {{ v.name }}<span v-if="v.city"> - {{ v.city }}</span>
              </option>
            </select>
            <p v-if="loadingVenues" class="text-[10px] text-base-content/60 mt-1">
              Loading venues...
            </p>
          </div>
        </div>

        <!-- Teams -->
        <div class="card bg-base-100 border border-base-300 p-3 md:p-4 space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1.5">
            <div class="text-[10px] md:text-[11px] uppercase tracking-wide text-base-content/60">
              Teams (from selected competition)
            </div>
            <div
                v-if="form.homeTeamId && form.awayTeamId && form.homeTeamId === form.awayTeamId"
                class="text-[10px] text-error"
            >
              Home and away team must be different.
            </div>
          </div>

          <div class="grid gap-3 md:gap-4 md:grid-cols-2">
            <div>
              <label class="label py-1">
                <span class="label-text text-[10px] md:text-xs">Home team</span>
              </label>
              <select
                  v-model="form.homeTeamId"
                  class="select select-bordered select-sm md:select-md w-full"
                  :disabled="!form.competitionId || loadingMeta || !teams.length"
                  required
              >
                <option value="" disabled>Select home team</option>
                <option v-for="t in teams" :key="t.id" :value="t.id">
                  {{ t.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="label py-1">
                <span class="label-text text-[10px] md:text-xs">Away team</span>
              </label>
              <select
                  v-model="form.awayTeamId"
                  class="select select-bordered select-sm md:select-md w-full"
                  :disabled="!form.competitionId || loadingMeta || !teams.length"
                  required
              >
                <option value="" disabled>Select away team</option>
                <option v-for="t in teams" :key="t.id" :value="t.id">
                  {{ t.name }}
                </option>
              </select>
            </div>
          </div>

          <p
              v-if="!teams.length && form.competitionId && !loadingMeta"
              class="text-[10px] text-base-content/60"
          >
            No teams found for this competition.
          </p>
        </div>

        <!-- Actions -->
        <div class="flex flex-col sm:flex-row justify-end gap-2 pt-2">
          <button
              type="reset"
              class="btn btn-ghost btn-sm md:btn-md w-full sm:w-auto"
              :disabled="submitting"
              @click.prevent="resetForm"
          >
            Reset
          </button>
          <button
              type="submit"
              class="btn btn-info btn-sm md:btn-md text-info-content w-full sm:w-auto"
              :disabled="!canSubmit"
          >
            <span
                v-if="submitting"
                class="loading loading-spinner loading-xs mr-1"
            />
            Create event
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
