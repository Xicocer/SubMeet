<script setup lang="ts">
import { computed } from 'vue'
import type { BookingHallLayout, BookingLayoutElement } from '@/types/booking'
import { DEFAULT_CANVAS_SIZE, getDefaultElementSize, getElementLabel } from '@/utils/halls'

const props = withDefaults(
  defineProps<{
    layout: BookingHallLayout
    selectedElementId?: string | null
  }>(),
  {
    selectedElementId: null,
  },
)

const emit = defineEmits<{
  (event: 'select', elementId: string | null): void
}>()

const canvasWidth = computed(() => props.layout.canvas?.width ?? DEFAULT_CANVAS_SIZE.width)
const canvasHeight = computed(() => props.layout.canvas?.height ?? DEFAULT_CANVAS_SIZE.height)

const getElementWidth = (element: BookingLayoutElement) => {
  return element.width ?? getDefaultElementSize(element.type).width
}

const getElementHeight = (element: BookingLayoutElement) => {
  return element.height ?? getDefaultElementSize(element.type).height
}

const getElementStyle = (element: BookingLayoutElement) => {
  return {
    left: `${element.x}px`,
    top: `${element.y}px`,
    width: `${getElementWidth(element)}px`,
    height: `${getElementHeight(element)}px`,
  }
}

const isBookable = (element: BookingLayoutElement) => {
  return element.type === 'seat' || element.type === 'vip_seat' || element.type === 'dancefloor'
}

const isAvailable = (element: BookingLayoutElement) => {
  if (element.type === 'dancefloor') {
    return Number(element.capacity_available ?? 0) > 0
  }

  return (element.booking_state ?? 'free') === 'free'
}

const isSelected = (element: BookingLayoutElement) => {
  return props.selectedElementId === element.id
}

const elementClasses = (element: BookingLayoutElement) => {
  if (element.type === 'stage') {
    return 'border-slate-950 bg-slate-950 text-white cursor-default'
  }

  if (isSelected(element)) {
    return 'border-amber-400 bg-amber-100 text-amber-950 ring-4 ring-amber-300/40 shadow-[0_16px_40px_-28px_rgba(217,119,6,0.7)]'
  }

  if (!isAvailable(element)) {
    return 'border-slate-300 bg-slate-200 text-slate-500 cursor-not-allowed'
  }

  if (element.type === 'vip_seat') {
    return 'border-sky-500 bg-sky-100 text-sky-950 cursor-pointer hover:border-sky-600 hover:bg-sky-200'
  }

  if (element.type === 'dancefloor') {
    return 'border-cyan-400 bg-cyan-100 text-cyan-950 cursor-pointer hover:border-cyan-500 hover:bg-cyan-200'
  }

  return 'border-blue-500 bg-blue-100 text-blue-950 cursor-pointer hover:border-blue-600 hover:bg-blue-200'
}

const onCanvasClick = () => {
  emit('select', null)
}

const onElementClick = (element: BookingLayoutElement) => {
  if (!isBookable(element) || !isAvailable(element)) {
    return
  }

  emit('select', isSelected(element) ? null : element.id)
}
</script>

<template>
  <div class="overflow-auto rounded-[1.75rem] border border-slate-300/70 bg-[#dbe7f4] p-4 sm:p-5">
    <div class="flex min-h-[680px] min-w-max items-start justify-center rounded-[1.5rem] border border-white/70 bg-[radial-gradient(circle_at_top,rgba(255,255,255,0.96),rgba(226,232,240,0.96)_48%,rgba(203,213,225,0.96)_100%)] p-6 sm:p-8">
      <div
        class="relative overflow-hidden rounded-[1.5rem] border border-slate-300 bg-white shadow-[0_35px_90px_-45px_rgba(15,23,42,0.45)]"
        :style="{
          width: `${canvasWidth}px`,
          height: `${canvasHeight}px`,
          backgroundImage:
            'linear-gradient(to right, rgba(148, 163, 184, 0.15) 1px, transparent 1px), linear-gradient(to bottom, rgba(148, 163, 184, 0.15) 1px, transparent 1px)',
          backgroundSize: '20px 20px',
        }"
        @click="onCanvasClick"
      >
        <div class="pointer-events-none absolute left-4 top-4 flex flex-wrap gap-2">
          <span
            v-for="level in layout.levels ?? []"
            :key="level.id"
            class="rounded-full border border-slate-200 bg-white/92 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 shadow-sm"
          >
            {{ level.name }}
          </span>
        </div>

        <button
          v-for="element in layout.elements"
          :key="element.id"
          type="button"
          class="absolute flex items-center justify-center rounded-2xl border px-2 text-center text-[10px] font-semibold leading-tight shadow-sm transition duration-150"
          :class="elementClasses(element)"
          :style="getElementStyle(element)"
          @click.stop="onElementClick(element)"
        >
          <div>
            <div>{{ getElementLabel(element) }}</div>
            <div v-if="element.type === 'dancefloor'" class="mt-1 text-[10px] font-medium opacity-80">
              {{ element.capacity_available ?? 0 }} / {{ element.capacity_total ?? 0 }}
            </div>
          </div>
        </button>
      </div>
    </div>
  </div>
</template>
