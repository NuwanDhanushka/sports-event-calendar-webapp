<script setup>
import "cally";
import { ref, computed, watch } from 'vue';

const props = defineProps({
  modelValue: {
    type: [String, Object],
    default: ''
  },
  idSuffix: { type: String, default: '' }
});
const emit = defineEmits(['update:modelValue', 'apply', 'clear']);

const POPOVER_ID = `input-calendar-popover${props.idSuffix ? '-' + props.idSuffix : ''}`;
const TRIGGER_ID = `input-calendar-trigger${props.idSuffix ? '-' + props.idSuffix : ''}`;

function toRangeString(value) {
  const start = value?.start || '';
  const end = value?.end   || '';
  return start && end ? `${start}/${end}` : '';
}

function parseModel(value) {
  if (!value) return { start: null, end: null };
  if (typeof value === 'string') {
    const [start, end] = value.split('/').map(data => (data || '').trim());
    return {
      start: start || null,
      end: end || null
    }
  }
  if (typeof value === 'object') {
    const start = value.start || null;
    const end = value.end || null;
    return { start: start || null, end: end || null };
  }
  return { start: null, end: null };
}

function formatDate(iso) {
  const [year, month, day] = String(iso).split('-').map(n => parseInt(n, 10));
  if (!year || !month || !day) return '';
  return new Date(year, month - 1, day).toLocaleDateString(undefined, {
    day: '2-digit', month: 'short', year: 'numeric'
  });
}

const selection = ref(parseModel(props.modelValue));

watch(() => props.modelValue, (value) => {
  const next = parseModel(value);
  if (next.start !== selection.value.start || next.end !== selection.value.end) {
    selection.value = next;
  }
})

const buttonText = computed(() => {
  const { start, end } = selection.value;
  return (start && end) ? `${formatDate(start)} - ${formatDate(end)}` : 'Date range';
});

const isEmpty = computed(() => !(selection.value.start && selection.value.end));

const calendarValue = computed({
  get() {
    return toRangeString(selection.value);
  },
  set(str) {
    const [start, end] = (str || '').split('/');
    selection.value = { start: start || null, end: end || null };
    emit('update:modelValue', toRangeString(selection.value));
  }
});

function handleCalendarChange(ev) {
  const detail = ev?.detail;
  const string =
      (typeof detail === 'string' && detail) ||
      (typeof detail?.value === 'string' && detail.value) ||
      ev?.target?.value ||
      ev?.currentTarget?.getAttribute?.('value') ||
      '';
  calendarValue.value = string;
}

function clearSelection() {
  selection.value = { start: null, end: null };
  emit('update:modelValue', '');
  emit('clear');
}

function applySelectionAndClose() {
  emit('update:modelValue', toRangeString(selection.value));
  emit('apply', { ...selection.value });
  document.getElementById(POPOVER_ID)?.hidePopover?.();
}

</script>

<template>
  <!-- Trigger -->
  <button
      :id="TRIGGER_ID"
      :popovertarget="POPOVER_ID"
      class="input input-bordered w-72 flex items-center justify-between gap-2"
      style="anchor-name: --input-calendar-anchor"
      type="button"
      aria-haspopup="dialog"
  >
    <span
        class="flex items-center gap-3 transition-colors duration-200"
        :class="isEmpty ? 'text-base-content/60' : 'text-base-content'"
    >
      <!-- calendar icon -->
      <svg
          xmlns="http://www.w3.org/2000/svg"
          class="size-4"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
      >
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M8 2v3m8-3v3M3 9h18M5 7h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2z"/>
      </svg>
      <span class="truncate">{{ buttonText }}</span>
    </span>

    <!-- chevron -->
    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 opacity-70"
         viewBox="0 0 24 24" fill="none" stroke="currentColor">
      <path d="M6 9l6 6 6-6"/>
    </svg>
  </button>

  <!-- Popover -->
  <div
      popover
      :id="POPOVER_ID"
      class="dropdown bg-base-100 rounded-box shadow-lg p-3 calendar-popover"
      style="position-anchor: --input-calendar-anchor"
  >
    <calendar-range
        class="cally"
        :value="calendarValue"
        @change="handleCalendarChange"
    >
      <!-- nav slots -->
      <svg aria-label="Previous" class="fill-current size-4" slot="previous"
           xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
        <path d="M15.75 19.5 8.25 12l7.5-7.5"/>
      </svg>
      <svg aria-label="Next" class="fill-current size-4" slot="next"
           xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
        <path d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
      </svg>

      <!-- months -->
      <div class="flex flex-row gap-5">
        <calendar-month />
        <calendar-month :offset="1" />
      </div>
    </calendar-range>

    <!-- Footer actions -->
    <div class="mt-3 flex items-center justify-end gap-2">
      <button class="btn btn-ghost btn-sm" type="button" @click="clearSelection">Clear</button>
      <button
          class="btn btn-primary btn-sm"
          type="button"
          :disabled="!selection.start || !selection.end"
          @click="applySelectionAndClose"
      >
        Apply
      </button>
    </div>
  </div>
</template>

<style scoped>
.calendar-popover { inline-size: max-content; }
</style>
