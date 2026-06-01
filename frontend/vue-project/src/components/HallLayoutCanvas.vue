<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import type {
  HallCanvasDropPayload,
  HallCanvasMovePayload,
  HallCanvasResizePayload,
  HallCanvasSelectionPayload,
  HallElementType,
  HallLayout,
  HallLayoutElement,
} from '@/types/hall'
import {
  clampToRange,
  DEFAULT_CANVAS_SIZE,
  getDefaultElementSize,
  getElementLabel,
  snapToGrid,
} from '@/utils/halls'

type ResizeAxis = 'x' | 'y' | 'both'

interface ElementGeometry {
  x: number
  y: number
  width: number
  height: number
}

interface ActiveInteraction {
  mode: 'move' | 'resize'
  axis?: ResizeAxis
  elementId: string
  pointerId: number
  started: boolean
  startClientX: number
  startClientY: number
  origin: ElementGeometry
  preview: ElementGeometry
}

const RESIZABLE_TYPES = new Set<HallElementType>(['stage', 'dancefloor', 'table'])

const props = withDefaults(
  defineProps<{
    layout: HallLayout
    selectedElementIds?: string[]
    readonly?: boolean
  }>(),
  {
    selectedElementIds: () => [],
    readonly: false,
  },
)

const emit = defineEmits<{
  (event: 'select', payload: HallCanvasSelectionPayload): void
  (event: 'drop-item', payload: HallCanvasDropPayload): void
  (event: 'interaction-start'): void
  (event: 'move-element', payload: HallCanvasMovePayload): void
  (event: 'resize-element', payload: HallCanvasResizePayload): void
}>()

const canvasRef = ref<HTMLElement | null>(null)
const activeInteraction = ref<ActiveInteraction | null>(null)

const canvasWidth = computed(() => props.layout.canvas?.width ?? DEFAULT_CANVAS_SIZE.width)
const canvasHeight = computed(() => props.layout.canvas?.height ?? DEFAULT_CANVAS_SIZE.height)
const isSelected = (elementId: string) => props.selectedElementIds.includes(elementId)

const getElementWidth = (element: HallLayoutElement) => {
  return element.width ?? getDefaultElementSize(element.type).width
}

const getElementHeight = (element: HallLayoutElement) => {
  return element.height ?? getDefaultElementSize(element.type).height
}

const getRenderedGeometry = (element: HallLayoutElement): ElementGeometry => {
  if (activeInteraction.value?.elementId === element.id) {
    return activeInteraction.value.preview
  }

  return {
    x: element.x,
    y: element.y,
    width: getElementWidth(element),
    height: getElementHeight(element),
  }
}

const getElementStyle = (element: HallLayoutElement) => {
  const geometry = getRenderedGeometry(element)

  return {
    left: `${geometry.x}px`,
    top: `${geometry.y}px`,
    width: `${geometry.width}px`,
    height: `${geometry.height}px`,
  }
}

const getPointerPosition = (event: DragEvent) => {
  const canvas = canvasRef.value

  if (!canvas) {
    return null
  }

  const bounds = canvas.getBoundingClientRect()
  const x = event.clientX - bounds.left
  const y = event.clientY - bounds.top

  return {
    x: Math.max(0, x),
    y: Math.max(0, y),
  }
}

const getResizableMinSize = (element: HallLayoutElement) => {
  if (element.type === 'stage') {
    return { width: 160, height: 60 }
  }

  if (element.type === 'table') {
    return { width: 70, height: 60 }
  }

  return { width: 120, height: 80 }
}

const buildMovePreview = (interaction: ActiveInteraction, deltaX: number, deltaY: number): ElementGeometry => {
  const maxX = Math.max(0, canvasWidth.value - interaction.origin.width)
  const maxY = Math.max(0, canvasHeight.value - interaction.origin.height)

  return {
    x: clampToRange(snapToGrid(interaction.origin.x + deltaX), 0, maxX),
    y: clampToRange(snapToGrid(interaction.origin.y + deltaY), 0, maxY),
    width: interaction.origin.width,
    height: interaction.origin.height,
  }
}

const buildResizePreview = (interaction: ActiveInteraction, deltaX: number, deltaY: number) => {
  const element = props.layout.elements.find((item) => item.id === interaction.elementId)

  if (!element) {
    return interaction.preview
  }

  const minSize = getResizableMinSize(element)
  const maxWidth = Math.max(minSize.width, canvasWidth.value - interaction.origin.x)
  const maxHeight = Math.max(minSize.height, canvasHeight.value - interaction.origin.y)

  let width = interaction.origin.width
  let height = interaction.origin.height

  if (interaction.axis === 'x' || interaction.axis === 'both') {
    width = clampToRange(snapToGrid(interaction.origin.width + deltaX), minSize.width, maxWidth)
  }

  if (interaction.axis === 'y' || interaction.axis === 'both') {
    height = clampToRange(snapToGrid(interaction.origin.height + deltaY), minSize.height, maxHeight)
  }

  return {
    x: interaction.origin.x,
    y: interaction.origin.y,
    width,
    height,
  }
}

const geometryChanged = (left: ElementGeometry, right: ElementGeometry) => {
  return (
    left.x !== right.x ||
    left.y !== right.y ||
    left.width !== right.width ||
    left.height !== right.height
  )
}

const updateInteractionPreview = (clientX: number, clientY: number) => {
  const interaction = activeInteraction.value

  if (!interaction) {
    return
  }

  const deltaX = clientX - interaction.startClientX
  const deltaY = clientY - interaction.startClientY
  const nextPreview =
    interaction.mode === 'move'
      ? buildMovePreview(interaction, deltaX, deltaY)
      : buildResizePreview(interaction, deltaX, deltaY)

  if (!interaction.started && geometryChanged(nextPreview, interaction.origin)) {
    emit('interaction-start')
    interaction.started = true
  }

  interaction.preview = nextPreview
}

const unbindWindowListeners = () => {
  window.removeEventListener('pointermove', onWindowPointerMove)
  window.removeEventListener('pointerup', onWindowPointerUp)
  window.removeEventListener('pointercancel', onWindowPointerUp)
}

const finishInteraction = (cancelled = false) => {
  const interaction = activeInteraction.value

  if (!interaction) {
    return
  }

  unbindWindowListeners()

  if (!cancelled && interaction.started) {
    if (interaction.mode === 'move') {
      emit('move-element', {
        elementId: interaction.elementId,
        x: interaction.preview.x,
        y: interaction.preview.y,
      })
    } else {
      emit('resize-element', {
        elementId: interaction.elementId,
        x: interaction.preview.x,
        y: interaction.preview.y,
        width: interaction.preview.width,
        height: interaction.preview.height,
      })
    }
  }

  activeInteraction.value = null
}

function onWindowPointerMove(event: PointerEvent) {
  if (!activeInteraction.value || event.pointerId !== activeInteraction.value.pointerId) {
    return
  }

  event.preventDefault()
  updateInteractionPreview(event.clientX, event.clientY)
}

function onWindowPointerUp(event: PointerEvent) {
  if (!activeInteraction.value || event.pointerId !== activeInteraction.value.pointerId) {
    return
  }

  finishInteraction(event.type === 'pointercancel')
}

const bindWindowListeners = () => {
  window.addEventListener('pointermove', onWindowPointerMove)
  window.addEventListener('pointerup', onWindowPointerUp)
  window.addEventListener('pointercancel', onWindowPointerUp)
}

const startInteraction = (
  event: PointerEvent,
  element: HallLayoutElement,
  mode: ActiveInteraction['mode'],
  axis?: ResizeAxis,
) => {
  if (props.readonly || event.button !== 0) {
    return
  }

  if (event.shiftKey) {
    return
  }

  const origin: ElementGeometry = {
    x: element.x,
    y: element.y,
    width: getElementWidth(element),
    height: getElementHeight(element),
  }

  finishInteraction(true)
  emit('select', { elementId: element.id, additive: false })

  activeInteraction.value = {
    mode,
    axis,
    elementId: element.id,
    pointerId: event.pointerId,
    started: false,
    startClientX: event.clientX,
    startClientY: event.clientY,
    origin,
    preview: { ...origin },
  }

  bindWindowListeners()
  event.preventDefault()
}

const startMove = (event: PointerEvent, element: HallLayoutElement) => {
  startInteraction(event, element, 'move')
}

const startResize = (event: PointerEvent, element: HallLayoutElement, axis: ResizeAxis) => {
  if (!RESIZABLE_TYPES.has(element.type)) {
    return
  }

  startInteraction(event, element, 'resize', axis)
}

const onCanvasDrop = (event: DragEvent) => {
  if (props.readonly) {
    return
  }

  event.preventDefault()

  const position = getPointerPosition(event)

  if (!position || !event.dataTransfer) {
    return
  }

  const paletteType = event.dataTransfer.getData('application/x-hall-palette')

  if (paletteType) {
    emit('drop-item', {
      mode: 'create',
      type: paletteType as HallCanvasDropPayload['type'],
      x: position.x,
      y: position.y,
    })
  }
}

const onCanvasClick = () => {
  emit('select', { elementId: null, additive: false })
}

const onElementClick = (event: MouseEvent, element: HallLayoutElement) => {
  emit('select', {
    elementId: element.id,
    additive: event.shiftKey,
  })
}

const isResizable = (element: HallLayoutElement) => {
  return RESIZABLE_TYPES.has(element.type)
}

const elementClasses = (element: HallLayoutElement) => {
  if (element.type === 'stage') {
    return 'border-slate-950 bg-slate-950 text-white'
  }

  if (element.type === 'dancefloor') {
    return 'border-emerald-300 bg-emerald-100/90 text-emerald-950'
  }

  if (element.type === 'table') {
    return 'border-orange-300 bg-orange-100/90 text-orange-950'
  }

  if (element.type === 'vip_seat') {
    return 'border-amber-300 bg-amber-50 text-amber-950'
  }

  return 'border-sky-200 bg-sky-50 text-slate-900'
}

onBeforeUnmount(() => {
  finishInteraction(true)
})
</script>

<template>
  <div class="overflow-auto rounded-[1.75rem] border border-slate-300/70 bg-[#dbe3ee] p-4 sm:p-5">
    <div class="flex min-h-[760px] min-w-max items-start justify-center rounded-[1.5rem] border border-white/70 bg-[radial-gradient(circle_at_top,rgba(255,255,255,0.95),rgba(226,232,240,0.96)_48%,rgba(203,213,225,0.96)_100%)] p-6 sm:p-8">
      <div
        ref="canvasRef"
        class="relative overflow-hidden rounded-[1.5rem] border border-slate-300 bg-white shadow-[0_35px_90px_-45px_rgba(15,23,42,0.45)]"
        :style="{
          width: `${canvasWidth}px`,
          height: `${canvasHeight}px`,
          backgroundImage:
            'linear-gradient(to right, rgba(148, 163, 184, 0.15) 1px, transparent 1px), linear-gradient(to bottom, rgba(148, 163, 184, 0.15) 1px, transparent 1px)',
          backgroundSize: '20px 20px',
        }"
        @click="onCanvasClick"
        @dragover.prevent
        @drop="onCanvasDrop"
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
          class="absolute flex select-none items-center justify-center rounded-2xl border text-center text-[11px] font-semibold leading-tight shadow-sm transition duration-150 touch-none"
          :class="[
            elementClasses(element),
            isSelected(element.id) ? 'ring-4 ring-sky-400/35 shadow-[0_18px_40px_-25px_rgba(14,165,233,0.7)]' : '',
            readonly ? 'cursor-default' : 'cursor-grab active:cursor-grabbing',
          ]"
          :style="getElementStyle(element)"
          @click.stop="onElementClick($event, element)"
          @pointerdown.stop="startMove($event, element)"
        >
          <div class="px-2">
            <div>{{ getElementLabel(element) }}</div>
            <div v-if="element.type === 'dancefloor' || element.type === 'table'" class="mt-1 text-[10px] font-medium opacity-75">
              {{ element.type === 'table' ? `${element.capacity ?? 2} мест` : `до ${element.capacity ?? 0} человек` }}
            </div>
          </div>

          <template v-if="isSelected(element.id) && props.selectedElementIds.length === 1 && isResizable(element) && !readonly">
            <span
              class="absolute -right-2 top-1/2 h-12 w-3 -translate-y-1/2 rounded-full border border-white bg-slate-900 shadow-lg shadow-slate-900/15 cursor-ew-resize"
              @click.stop
              @pointerdown.stop.prevent="startResize($event, element, 'x')"
            ></span>
            <span
              class="absolute bottom-[-0.5rem] left-1/2 h-3 w-12 -translate-x-1/2 rounded-full border border-white bg-slate-900 shadow-lg shadow-slate-900/15 cursor-ns-resize"
              @click.stop
              @pointerdown.stop.prevent="startResize($event, element, 'y')"
            ></span>
            <span
              class="absolute -bottom-2 -right-2 h-4 w-4 rounded-full border-2 border-white bg-slate-900 shadow-lg shadow-slate-900/15 cursor-se-resize"
              @click.stop
              @pointerdown.stop.prevent="startResize($event, element, 'both')"
            ></span>
          </template>
        </button>

        <div
          v-if="layout.elements.length === 0"
          class="pointer-events-none absolute inset-0 flex items-center justify-center px-6 text-center text-sm text-slate-400"
        >
          Перетащи элементы из палитры в сетку, чтобы собрать схему зала.
        </div>
      </div>
    </div>
  </div>
</template>
