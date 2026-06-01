<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  archiveOrganizerHallRequest,
  createOrganizerHallRequest,
  getOrganizerHallRequest,
  updateOrganizerHallRequest,
} from '@/api/halls'
import HallLayoutCanvas from '@/components/HallLayoutCanvas.vue'
import type {
  EditableHallStatus,
  HallCanvasDropPayload,
  HallCanvasMovePayload,
  HallCanvasResizePayload,
  HallCanvasSelectionPayload,
  HallDetails,
  HallElementType,
  HallLayout,
  HallLayoutElement,
} from '@/types/hall'
import {
  clampToRange,
  cloneHallLayout,
  createDefaultHallLayout,
  createHallElement,
  createHallLevel,
  getDefaultElementSize,
  normalizeHallLayout,
  snapToGrid,
  summarizeHallLayout,
} from '@/utils/halls'

const route = useRoute()
const router = useRouter()

const detailLoading = ref(false)
const saving = ref(false)
const error = ref('')
const success = ref('')
const selectedElementIds = ref<string[]>([])

const createHallDraft = () => ({
  name: '',
  address: '',
  description: '',
  photo_urls: '',
  hourly_rate: '',
  status: 'draft' as EditableHallStatus,
})

const hallForm = reactive(createHallDraft())
const layoutDraft = ref<HallLayout>(createDefaultHallLayout())
const layoutHistory = ref<HallLayout[]>([])
const MAX_LAYOUT_HISTORY = 60

const isEditMode = computed(() => route.name === 'venue-hall-edit')
const editorTitle = computed(() => {
  return isEditMode.value ? hallForm.name.trim() || 'Редактирование зала' : 'Новый зал'
})

const selectedElement = computed(() => {
  if (selectedElementIds.value.length !== 1) {
    return null
  }

  return layoutDraft.value.elements.find((element) => element.id === selectedElementIds.value[0]) ?? null
})

const selectedElements = computed(() => {
  const selectedIds = new Set(selectedElementIds.value)
  return layoutDraft.value.elements.filter((element) => selectedIds.has(element.id))
})

const selectedElementsCount = computed(() => selectedElements.value.length)
const hasMultipleSelection = computed(() => selectedElementsCount.value > 1)

const orderedLevels = computed(() => {
  return [...layoutDraft.value.levels].sort((left, right) => (left.order ?? 0) - (right.order ?? 0))
})

const layoutSummary = computed(() => summarizeHallLayout(layoutDraft.value))
const hasStage = computed(() => layoutSummary.value.hasStage)
const canSaveHall = computed(() => {
  return (
    hallForm.name.trim() !== '' &&
    hallForm.address.trim() !== '' &&
    Number(String(hallForm.hourly_rate).replace(',', '.')) > 0 &&
    layoutSummary.value.totalCapacity > 0
  )
})
const canUndo = computed(() => layoutHistory.value.length > 0)
const saveActionLabel = computed(() => {
  if (saving.value) {
    return 'Сохраняем...'
  }

  return isEditMode.value ? 'Сохранить изменения' : 'Создать зал'
})
const hallStatusLabel = computed(() => {
  return hallForm.status === 'active' ? 'Активный' : 'Черновик'
})
const hallStatusClasses = computed(() => {
  return hallForm.status === 'active'
    ? 'border-emerald-400/30 bg-emerald-400/15 text-emerald-100'
    : 'border-white/15 bg-white/10 text-slate-100'
})

const clearLayoutHistory = () => {
  layoutHistory.value = []
}

const rememberLayoutState = () => {
  layoutHistory.value = [...layoutHistory.value, cloneHallLayout(layoutDraft.value)].slice(
    -MAX_LAYOUT_HISTORY,
  )
}

const restoreLayoutSnapshot = (snapshot: HallLayout) => {
  layoutDraft.value = cloneHallLayout(snapshot)
  selectedElementIds.value = selectedElementIds.value.filter((selectedId) => {
    return layoutDraft.value.elements.some((element) => element.id === selectedId)
  })

  if (selectedElementIds.value.length === 0 && layoutDraft.value.elements[0]) {
    selectedElementIds.value = [layoutDraft.value.elements[0].id]
  }
}

const undoLayoutChange = () => {
  const previousSnapshot = layoutHistory.value.at(-1)

  if (!previousSnapshot) {
    return
  }

  layoutHistory.value = layoutHistory.value.slice(0, -1)
  restoreLayoutSnapshot(previousSnapshot)
  error.value = ''
  success.value = ''
}

const isEditableTarget = (target: EventTarget | null) => {
  return (
    target instanceof HTMLElement &&
    (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))
  )
}

const onWindowKeyDown = (event: KeyboardEvent) => {
  if (
    !(event.ctrlKey || event.metaKey) ||
    event.altKey ||
    event.shiftKey ||
    event.key.toLowerCase() !== 'z' ||
    isEditableTarget(event.target) ||
    layoutHistory.value.length === 0
  ) {
    return
  }

  event.preventDefault()
  undoLayoutChange()
}

const paletteItems: Array<{ type: HallElementType; title: string; description: string }> = [
  {
    type: 'stage',
    title: 'Сцена',
    description: 'Необязательный ориентир для концертных и театральных залов.',
  },
  {
    type: 'seat',
    title: 'Сидячее место',
    description: 'Обычные посадочные места для партеров, амфитеатров и балконов.',
  },
  {
    type: 'vip_seat',
    title: 'VIP-место',
    description: 'Премиальные места для отдельных зон и более дорогих рядов.',
  },
  {
    type: 'dancefloor',
    title: 'Танцпол',
    description: 'Стоячая зона перед сценой с ограничением по вместимости.',
  },
  {
    type: 'table',
    title: 'Столик',
    description: 'Один объект на схеме, внутри которого можно указать количество мест. По умолчанию 2.',
  },
]

const paletteShortcut = (type: HallElementType) => {
  switch (type) {
    case 'stage':
      return 'ST'
    case 'vip_seat':
      return 'VIP'
    case 'dancefloor':
      return 'DF'
    case 'table':
      return 'TB'
    default:
      return 'SE'
  }
}

const paletteButtonClasses = (type: HallElementType) => {
  switch (type) {
    case 'stage':
      return 'border-white/15 bg-white/10 hover:border-white/25 hover:bg-white/14'
    case 'vip_seat':
      return 'border-amber-300/25 bg-amber-300/10 hover:border-amber-300/40 hover:bg-amber-300/15'
    case 'dancefloor':
      return 'border-emerald-300/25 bg-emerald-300/10 hover:border-emerald-300/40 hover:bg-emerald-300/15'
    case 'table':
      return 'border-orange-300/25 bg-orange-300/10 hover:border-orange-300/40 hover:bg-orange-300/15'
    default:
      return 'border-sky-300/25 bg-sky-300/10 hover:border-sky-300/40 hover:bg-sky-300/15'
  }
}

const elementTypeLabel = (type: HallElementType) => {
  switch (type) {
    case 'stage':
      return 'Сцена'
    case 'vip_seat':
      return 'VIP-место'
    case 'dancefloor':
      return 'Танцпол'
    case 'table':
      return 'Столик'
    default:
      return 'Место'
  }
}

const parsePhotoUrls = (value: string) => {
  return Array.from(
    new Set(
      value
        .split(/[\n,]/)
        .map((url) => url.trim())
        .filter((url) => url.length > 0),
    ),
  ).slice(0, 8)
}

const extractErrorMessage = (requestError: any, fallback: string) => {
  const validationErrors = requestError?.response?.data?.errors

  if (validationErrors && typeof validationErrors === 'object') {
    const firstField = Object.values(validationErrors)[0]

    if (Array.isArray(firstField) && firstField.length > 0) {
      return String(firstField[0])
    }
  }

  return requestError?.response?.data?.message || fallback
}

const resetEditor = () => {
  Object.assign(hallForm, createHallDraft())
  layoutDraft.value = createDefaultHallLayout()
  clearLayoutHistory()
  selectedElementIds.value = layoutDraft.value.elements[0] ? [layoutDraft.value.elements[0].id] : []
  error.value = ''
  success.value = ''
}

const fillEditor = (hall: HallDetails) => {
  hallForm.name = hall.name
  hallForm.address = hall.address ?? ''
  hallForm.description = hall.description ?? ''
  hallForm.photo_urls = (hall.photo_urls ?? []).join('\n')
  hallForm.hourly_rate = String(hall.hourly_rate ?? '')
  hallForm.status = hall.status === 'active' ? 'active' : 'draft'
  layoutDraft.value = normalizeHallLayout(hall.layout)
  clearLayoutHistory()
  selectedElementIds.value = layoutDraft.value.elements[0] ? [layoutDraft.value.elements[0].id] : []
}

const loadHallDetails = async (hallId: number) => {
  detailLoading.value = true
  error.value = ''

  try {
    const hall = await getOrganizerHallRequest(hallId)
    fillEditor(hall)
  } catch (requestError) {
    console.error(requestError)
    error.value = extractErrorMessage(requestError, 'Не удалось загрузить зал для редактирования.')
  } finally {
    detailLoading.value = false
  }
}

const syncEditorFromRoute = async () => {
  success.value = ''

  if (!isEditMode.value) {
    resetEditor()
    return
  }

  const hallId = Number(route.params.id)

  if (Number.isNaN(hallId) || hallId <= 0) {
    error.value = 'Некорректный идентификатор зала.'
    await router.replace({ name: 'venue-halls' })
    return
  }

  await loadHallDetails(hallId)
}

const updateElement = (
  elementId: string,
  updater: (element: HallLayoutElement) => HallLayoutElement,
) => {
  layoutDraft.value.elements = layoutDraft.value.elements.map((element) => {
    return element.id === elementId ? updater(element) : element
  })
}

const getNextSeatLabel = (type: HallElementType) => {
  const count = layoutDraft.value.elements.filter((element) => element.type === type).length + 1

  if (type === 'vip_seat') {
    return {
      row: 'VIP',
      number: String(count),
      label: `VIP-${count}`,
    }
  }

  return {
    row: 'A',
    number: String(count),
    label: `A-${count}`,
  }
}

const createPlacedElement = (type: HallElementType, x: number, y: number) => {
  const canvasWidth = layoutDraft.value.canvas.width
  const canvasHeight = layoutDraft.value.canvas.height
  const size = getDefaultElementSize(type)

  const originX = clampToRange(snapToGrid(x - size.width / 2), 0, canvasWidth - size.width)
  const originY = clampToRange(snapToGrid(y - size.height / 2), 0, canvasHeight - size.height)

  const defaultLevelId =
    type === 'seat' || type === 'vip_seat' || type === 'table'
      ? orderedLevels.value[0]?.id ?? null
      : null

  const seatLabel = type === 'seat' || type === 'vip_seat' ? getNextSeatLabel(type) : null
  const tableIndex = type === 'table'
    ? layoutDraft.value.elements.filter((element) => element.type === 'table').length + 1
    : null

  const overrides: Partial<HallLayoutElement> = {
    x: originX,
    y: originY,
    level_id: defaultLevelId,
    row: seatLabel?.row,
    number: seatLabel?.number,
    label: tableIndex !== null ? `Столик ${tableIndex}` : seatLabel?.label,
  }

  if (type === 'table') {
    overrides.capacity = 2
  }

  return createHallElement(type, overrides)
}

const ensureElementTypeAllowed = (type: HallElementType) => {
  if (type === 'stage' && layoutDraft.value.elements.some((element) => element.type === 'stage')) {
    error.value = 'Для одного зала в MVP поддерживается только одна сцена.'
    return false
  }

  if (
    type === 'dancefloor' &&
    layoutDraft.value.elements.some((element) => element.type === 'dancefloor')
  ) {
    error.value = 'Для одного зала в MVP поддерживается только один танцпол.'
    return false
  }

  return true
}

const addElementToLayout = (type: HallElementType, x: number, y: number) => {
  if (!ensureElementTypeAllowed(type)) {
    return
  }

  rememberLayoutState()
  const element = createPlacedElement(type, x, y)
  layoutDraft.value.elements = [...layoutDraft.value.elements, element]
  selectedElementIds.value = [element.id]
  error.value = ''
}

const quickAddElement = (type: HallElementType) => {
  addElementToLayout(type, layoutDraft.value.canvas.width / 2, layoutDraft.value.canvas.height / 2)
}

const startPaletteDrag = (event: DragEvent, type: HallElementType) => {
  if (!event.dataTransfer) {
    return
  }

  event.dataTransfer.effectAllowed = 'copy'
  event.dataTransfer.setData('application/x-hall-palette', type)
}

const setElementPosition = (elementId: string, x: number, y: number) => {
  const element = layoutDraft.value.elements.find((item) => item.id === elementId)

  if (!element) {
    return
  }

  const canvasWidth = layoutDraft.value.canvas.width
  const canvasHeight = layoutDraft.value.canvas.height
  const width = element.width ?? getDefaultElementSize(element.type).width
  const height = element.height ?? getDefaultElementSize(element.type).height

  updateElement(elementId, (currentElement) => ({
    ...currentElement,
    x: clampToRange(snapToGrid(x), 0, canvasWidth - width),
    y: clampToRange(snapToGrid(y), 0, canvasHeight - height),
  }))

  error.value = ''
}

const moveElementTo = (elementId: string, x: number, y: number) => {
  const element = layoutDraft.value.elements.find((item) => item.id === elementId)

  if (!element) {
    return
  }

  const width = element.width ?? getDefaultElementSize(element.type).width
  const height = element.height ?? getDefaultElementSize(element.type).height

  setElementPosition(elementId, x - width / 2, y - height / 2)
}

const resizeElement = (payload: HallCanvasResizePayload) => {
  const element = layoutDraft.value.elements.find((item) => item.id === payload.elementId)

  if (!element || (element.type !== 'stage' && element.type !== 'dancefloor' && element.type !== 'table')) {
    return
  }

  const canvasWidth = layoutDraft.value.canvas.width
  const canvasHeight = layoutDraft.value.canvas.height
  const minWidth = element.type === 'stage' ? 160 : element.type === 'table' ? 70 : 120
  const minHeight = element.type === 'stage' ? 60 : element.type === 'table' ? 60 : 80
  const nextX = clampToRange(snapToGrid(payload.x), 0, canvasWidth - minWidth)
  const nextY = clampToRange(snapToGrid(payload.y), 0, canvasHeight - minHeight)
  const nextWidth = clampToRange(snapToGrid(payload.width), minWidth, canvasWidth - nextX)
  const nextHeight = clampToRange(snapToGrid(payload.height), minHeight, canvasHeight - nextY)

  updateElement(payload.elementId, (currentElement) => ({
    ...currentElement,
    x: nextX,
    y: nextY,
    width: nextWidth,
    height: nextHeight,
  }))

  error.value = ''
}

const onCanvasMove = (payload: HallCanvasMovePayload) => {
  setElementPosition(payload.elementId, payload.x, payload.y)
}

const onCanvasResize = (payload: HallCanvasResizePayload) => {
  resizeElement(payload)
}

const onCanvasSelect = (payload: HallCanvasSelectionPayload) => {
  if (payload.elementId === null) {
    selectedElementIds.value = []
    return
  }

  if (payload.additive) {
    selectedElementIds.value = selectedElementIds.value.includes(payload.elementId)
      ? selectedElementIds.value.filter((selectedId) => selectedId !== payload.elementId)
      : [...selectedElementIds.value, payload.elementId]
    return
  }

  selectedElementIds.value = [payload.elementId]
}

const onCanvasDrop = (payload: HallCanvasDropPayload) => {
  if (payload.mode === 'create' && payload.type) {
    addElementToLayout(payload.type, payload.x, payload.y)
    return
  }

  if (payload.mode === 'move' && payload.elementId) {
    moveElementTo(payload.elementId, payload.x, payload.y)
  }
}

const addLevel = () => {
  const nextOrder = orderedLevels.value.length + 1
  rememberLayoutState()
  layoutDraft.value.levels = [...orderedLevels.value, createHallLevel(`Уровень ${nextOrder}`, nextOrder)]
}

const removeLevel = (levelId: string) => {
  if (layoutDraft.value.levels.length <= 1) {
    error.value = 'Оставь хотя бы один уровень, чтобы не потерять структуру зала.'
    return
  }

  rememberLayoutState()
  layoutDraft.value.levels = orderedLevels.value
    .filter((level) => level.id !== levelId)
    .map((level, index) => ({
      ...level,
      order: index + 1,
    }))

  layoutDraft.value.elements = layoutDraft.value.elements.map((element) => {
    return element.level_id === levelId
      ? { ...element, level_id: layoutDraft.value.levels[0]?.id ?? null }
      : element
  })
}

const removeSelectedElement = () => {
  if (selectedElements.value.length === 0) {
    return
  }

  rememberLayoutState()
  const removableIds = new Set(selectedElements.value.map((element) => element.id))
  layoutDraft.value.elements = layoutDraft.value.elements.filter((element) => !removableIds.has(element.id))
  selectedElementIds.value = selectedElementIds.value.filter((selectedId) => {
    return layoutDraft.value.elements.some((element) => element.id === selectedId)
  })

  if (selectedElementIds.value.length === 0 && layoutDraft.value.elements[0]) {
    selectedElementIds.value = [layoutDraft.value.elements[0].id]
  }
}

const saveHall = async () => {
  if (!canSaveHall.value) {
    error.value =
      'Чтобы сохранить зал, добавь название, адрес и хотя бы одну продаваемую зону.'
    return
  }

  saving.value = true
  error.value = ''
  success.value = ''

  try {
    const payload = {
      name: hallForm.name.trim(),
      address: hallForm.address.trim(),
      description: hallForm.description.trim() || null,
      photo_urls: parsePhotoUrls(hallForm.photo_urls),
      hourly_rate: Number(String(hallForm.hourly_rate).replace(',', '.')),
      status: hallForm.status,
      layout: cloneHallLayout(layoutDraft.value),
    }

    const response = isEditMode.value
      ? await updateOrganizerHallRequest(Number(route.params.id), payload)
      : await createOrganizerHallRequest(payload)

    success.value = response.message

    if (!isEditMode.value) {
      await router.replace({ name: 'venue-hall-edit', params: { id: response.hall.id } })
      return
    }

    fillEditor(response.hall)
  } catch (requestError) {
    console.error(requestError)
    error.value = extractErrorMessage(requestError, 'Не удалось сохранить зал.')
  } finally {
    saving.value = false
  }
}

const archiveHall = async () => {
  if (!isEditMode.value) {
    return
  }

  const confirmed = window.confirm('Отправить текущий зал в архив?')

  if (!confirmed) {
    return
  }

  saving.value = true
  error.value = ''
  success.value = ''

  try {
    const response = await archiveOrganizerHallRequest(Number(route.params.id))
    success.value = response.message
    fillEditor(response.hall)
  } catch (requestError) {
    console.error(requestError)
    error.value = extractErrorMessage(requestError, 'Не удалось отправить зал в архив.')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  window.addEventListener('keydown', onWindowKeyDown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onWindowKeyDown)
})

watch(
  () => route.fullPath,
  async () => {
    await syncEditorFromRoute()
  },
  { immediate: true },
)
</script>

<template>
  <div class="min-h-[calc(100vh-0.5rem)] overflow-hidden rounded-[2rem] border border-slate-300/70 bg-[#d7dee8] shadow-[0_40px_140px_-70px_rgba(15,23,42,0.55)]">
    <section class="border-b border-slate-900 bg-slate-950 px-4 py-3 text-white sm:px-5">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
          <div class="flex flex-wrap items-center gap-2">
            <RouterLink
              to="/profile"
              class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-white/90 transition hover:bg-white/10"
            >
              Профиль
            </RouterLink>
            <RouterLink
              to="/venue/halls"
              class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-white/90 transition hover:bg-white/10"
            >
              Залы
            </RouterLink>
          </div>

          <div class="hidden h-10 w-px bg-white/10 sm:block"></div>

          <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-white/45">
              {{ isEditMode ? 'Hall Editor' : 'New Hall' }}
            </p>
            <h2 class="mt-1 text-lg font-semibold tracking-tight text-white sm:text-xl">
              {{ editorTitle }}
            </h2>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <span
            class="rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em]"
            :class="hallStatusClasses"
          >
            {{ hallStatusLabel }}
          </span>
          <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-white/55">
            Ctrl+Z
          </span>
          <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-white/55">
            Shift+Click
          </span>
          <button
            type="button"
            class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="!canUndo"
            @click="undoLayoutChange"
          >
            Undo
          </button>
          <button
            type="button"
            class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-white/10"
            @click="resetEditor"
          >
            Сбросить
          </button>
          <button
            v-if="isEditMode"
            type="button"
            class="rounded-2xl border border-rose-400/25 bg-rose-400/10 px-4 py-2.5 text-sm font-medium text-rose-100 transition hover:bg-rose-400/15 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="saving"
            @click="archiveHall"
          >
            В архив
          </button>
          <button
            type="button"
            class="rounded-2xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:bg-white/40 disabled:text-slate-500"
            :disabled="saving || !canSaveHall || detailLoading"
            @click="saveHall"
          >
            {{ saveActionLabel }}
          </button>
        </div>
      </div>
    </section>

    <div
      v-if="success"
      class="border-b border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 sm:px-5"
    >
      {{ success }}
    </div>

    <div
      v-if="error"
      class="border-b border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900 sm:px-5"
    >
      {{ error }}
    </div>

    <section class="grid gap-0 xl:grid-cols-[88px_minmax(0,1fr)_360px]">
      <aside class="border-b border-slate-300/70 bg-slate-900 px-3 py-4 text-white xl:min-h-[calc(100vh-7rem)] xl:border-b-0 xl:border-r">
        <div class="flex gap-3 overflow-x-auto pb-1 xl:flex-col xl:overflow-visible">
          <button
            v-for="item in paletteItems"
            :key="item.type"
            type="button"
            class="group relative flex h-16 min-w-[4.5rem] shrink-0 flex-col items-center justify-center rounded-[1.35rem] border text-white transition"
            :class="paletteButtonClasses(item.type)"
            :title="item.title"
            draggable="true"
            @click="quickAddElement(item.type)"
            @dragstart="startPaletteDrag($event, item.type)"
          >
            <span class="text-[10px] font-semibold uppercase tracking-[0.24em] text-white/45">Tool</span>
            <span class="mt-1 text-sm font-semibold tracking-[0.16em]">{{ paletteShortcut(item.type) }}</span>

            <div class="pointer-events-none absolute left-full top-1/2 z-30 ml-3 hidden w-64 -translate-y-1/2 rounded-[1.25rem] border border-slate-200 bg-white px-4 py-3 text-left text-slate-900 opacity-0 shadow-[0_25px_60px_-35px_rgba(15,23,42,0.45)] transition duration-150 group-hover:opacity-100 xl:block">
              <p class="text-sm font-semibold">{{ item.title }}</p>
              <p class="mt-1 text-sm leading-6 text-slate-500">{{ item.description }}</p>
            </div>
          </button>
        </div>

        <div class="mt-4 flex gap-3 xl:flex-col">
          <button
            type="button"
            class="flex h-14 min-w-[4.5rem] shrink-0 flex-col items-center justify-center rounded-[1.35rem] border border-white/10 bg-white/5 text-white transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-35"
            :disabled="!canUndo"
            @click="undoLayoutChange"
          >
            <span class="text-[10px] font-semibold uppercase tracking-[0.24em] text-white/45">Edit</span>
            <span class="mt-1 text-sm font-semibold">Undo</span>
          </button>
        </div>
      </aside>

      <section class="min-w-0 p-3 sm:p-4">
        <div class="rounded-[1.75rem] border border-white/60 bg-white/40 p-3 shadow-inner shadow-white/60 sm:p-4">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Workspace</p>
              <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-950 sm:text-2xl">
                Рабочая область
              </h3>
            </div>

            <div class="flex flex-wrap gap-2">
              <span class="rounded-full border border-slate-200 bg-white/85 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                {{ layoutDraft.canvas.width }} x {{ layoutDraft.canvas.height }}
              </span>
              <span class="rounded-full border border-slate-200 bg-white/85 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                Capacity {{ layoutSummary.totalCapacity }}
              </span>
            </div>
          </div>

          <div class="mt-4 rounded-[1.6rem] border border-slate-200 bg-[#edf2f7] p-3 sm:p-4">
            <div class="mb-4 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
              <div class="flex flex-wrap gap-2">
                <span
                  v-for="level in orderedLevels"
                  :key="level.id"
                  class="rounded-full border border-white/90 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-slate-600 shadow-sm shadow-slate-900/5"
                >
                  {{ level.name }}
                </span>
                <button
                  type="button"
                  class="rounded-full border border-slate-300 bg-slate-900 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-slate-800"
                  @click="addLevel"
                >
                  + Уровень
                </button>
              </div>
            </div>

            <HallLayoutCanvas
              :layout="layoutDraft"
              :selected-element-ids="selectedElementIds"
              @select="onCanvasSelect"
              @drop-item="onCanvasDrop"
              @interaction-start="rememberLayoutState"
              @move-element="onCanvasMove"
              @resize-element="onCanvasResize"
            />
          </div>
        </div>
      </section>

      <aside class="border-t border-slate-300/70 bg-[#f7f9fc] p-4 sm:p-5 xl:max-h-[calc(100vh-7rem)] xl:overflow-y-auto xl:border-l xl:border-t-0">
        <div class="space-y-4">
          <section class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-900/5">
            <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Document</p>
            <div class="mt-4 grid gap-4">
              <div>
                <label class="field-label" for="hall-name">Название</label>
                <input
                  id="hall-name"
                  v-model="hallForm.name"
                  type="text"
                  class="field-input"
                  placeholder="Главная арена или камерный зал"
                />
              </div>

              <div>
                <label class="field-label" for="hall-address">Адрес площадки</label>
                <input
                  id="hall-address"
                  v-model="hallForm.address"
                  type="text"
                  class="field-input"
                  placeholder="Нижний Новгород, ул. Большая Покровская, 1"
                />
              </div>

              <div>
                <label class="field-label" for="hall-description">Описание</label>
                <textarea
                  id="hall-description"
                  v-model="hallForm.description"
                  rows="3"
                  class="field-input resize-none"
                  placeholder="Коротко опиши формат зала и ключевые особенности."
                ></textarea>
              </div>

              <div>
                <label class="field-label" for="hall-photos">Фото площадки</label>
                <textarea
                  id="hall-photos"
                  v-model="hallForm.photo_urls"
                  rows="3"
                  class="field-input resize-none"
                  placeholder="Вставь ссылки на фото, каждую с новой строки"
                ></textarea>
                <p class="mt-2 text-xs leading-5 text-slate-500">
                  До 8 изображений. Они появятся в карточке события, чтобы посетитель видел реальное место.
                </p>
              </div>

              <div>
                <label class="field-label" for="hall-hourly-rate">Ставка аренды в час</label>
                <input
                  id="hall-hourly-rate"
                  v-model="hallForm.hourly_rate"
                  type="text"
                  inputmode="decimal"
                  class="field-input"
                  placeholder="3500"
                />
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="field-label" for="hall-status">Статус</label>
                  <select id="hall-status" v-model="hallForm.status" class="field-input">
                    <option value="draft">Черновик</option>
                    <option value="active">Активный</option>
                  </select>
                </div>

                <div>
                  <label class="field-label" for="hall-width">Ширина</label>
                  <input
                    id="hall-width"
                    v-model.number="layoutDraft.canvas.width"
                    type="number"
                    min="640"
                    step="20"
                    class="field-input"
                  />
                </div>
              </div>

              <div>
                <label class="field-label" for="hall-height">Высота</label>
                <input
                  id="hall-height"
                  v-model.number="layoutDraft.canvas.height"
                  type="number"
                  min="420"
                  step="20"
                  class="field-input"
                />
              </div>
            </div>
          </section>

          <section class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-900/5">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Inspector</p>
                <p class="mt-2 text-lg font-semibold text-slate-950">
                  {{
                    selectedElement
                      ? elementTypeLabel(selectedElement.type)
                      : hasMultipleSelection
                        ? `Выбрано объектов: ${selectedElementsCount}`
                        : 'Ничего не выбрано'
                  }}
                </p>
              </div>

              <button
                v-if="selectedElementsCount > 0"
                type="button"
                class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-100"
                @click="removeSelectedElement"
              >
                {{ hasMultipleSelection ? 'Удалить выбранные' : 'Удалить' }}
              </button>
            </div>

            <div v-if="selectedElement" class="mt-4 grid gap-4 sm:grid-cols-2">
              <div class="sm:col-span-2">
                <label class="field-label" for="selected-element-label">Подпись</label>
                <input
                  id="selected-element-label"
                  v-model="selectedElement.label"
                  type="text"
                  class="field-input"
                />
              </div>

              <template v-if="selectedElement.type === 'seat' || selectedElement.type === 'vip_seat'">
                <div>
                  <label class="field-label" for="selected-element-row">Ряд</label>
                  <input
                    id="selected-element-row"
                    v-model="selectedElement.row"
                    type="text"
                    class="field-input"
                  />
                </div>

                <div>
                  <label class="field-label" for="selected-element-number">Номер</label>
                  <input
                    id="selected-element-number"
                    v-model="selectedElement.number"
                    type="text"
                    class="field-input"
                  />
                </div>

                <div class="sm:col-span-2">
                  <label class="field-label" for="selected-element-level">Уровень</label>
                  <select id="selected-element-level" v-model="selectedElement.level_id" class="field-input">
                    <option :value="null">Без уровня</option>
                    <option v-for="level in orderedLevels" :key="level.id" :value="level.id">
                      {{ level.name }}
                    </option>
                  </select>
                </div>
              </template>

              <div v-if="selectedElement.type === 'table'" class="sm:col-span-2">
                <label class="field-label" for="selected-element-level">Уровень</label>
                <select id="selected-element-level" v-model="selectedElement.level_id" class="field-input">
                  <option :value="null">Без уровня</option>
                  <option v-for="level in orderedLevels" :key="level.id" :value="level.id">
                    {{ level.name }}
                  </option>
                </select>
              </div>

              <div v-if="selectedElement.type === 'dancefloor' || selectedElement.type === 'table'" class="sm:col-span-2">
                <label class="field-label" for="selected-element-capacity">
                  {{ selectedElement.type === 'table' ? 'Количество мест за столиком' : 'Вместимость танцпола' }}
                </label>
                <input
                  id="selected-element-capacity"
                  v-model.number="selectedElement.capacity"
                  type="number"
                  min="1"
                  class="field-input"
                />
              </div>

              <div>
                <label class="field-label" for="selected-element-x">X</label>
                <input
                  id="selected-element-x"
                  v-model.number="selectedElement.x"
                  type="number"
                  min="0"
                  class="field-input"
                />
              </div>

              <div>
                <label class="field-label" for="selected-element-y">Y</label>
                <input
                  id="selected-element-y"
                  v-model.number="selectedElement.y"
                  type="number"
                  min="0"
                  class="field-input"
                />
              </div>

              <div>
                <label class="field-label" for="selected-element-width">Ширина</label>
                <input
                  id="selected-element-width"
                  v-model.number="selectedElement.width"
                  type="number"
                  min="20"
                  class="field-input"
                />
              </div>

              <div>
                <label class="field-label" for="selected-element-height">Высота</label>
                <input
                  id="selected-element-height"
                  v-model.number="selectedElement.height"
                  type="number"
                  min="20"
                  class="field-input"
                />
              </div>
            </div>

            <div
              v-else-if="hasMultipleSelection"
              class="mt-4 rounded-[1.4rem] border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-sm leading-6 text-slate-500"
            >
              <p class="font-semibold text-slate-700">Множественное выделение активно</p>
              <p class="mt-2">
                Сейчас через <span class="font-semibold text-slate-700">Shift + Click</span> можно собрать несколько объектов в одно выделение и удалить их пачкой.
              </p>
              <p class="mt-2">
                Batch-редактирование свойств мы спокойно добавим позже, когда будем пересобирать фронтенд.
              </p>
            </div>

            <div
              v-else
              class="mt-4 rounded-[1.4rem] border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-sm leading-6 text-slate-500"
            >
              Выбери объект на сцене, и справа появятся его свойства, как в обычном design-inspector.
            </div>
          </section>

          <section class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-900/5">
            <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Summary</p>
            <div class="mt-4 grid grid-cols-2 gap-3">
              <div class="rounded-[1.3rem] border border-slate-200 bg-slate-50 px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Seats</p>
                <p class="mt-2 text-2xl font-semibold text-slate-950">{{ layoutSummary.seatCount }}</p>
              </div>
              <div class="rounded-[1.3rem] border border-slate-200 bg-slate-50 px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Tables</p>
                <p class="mt-2 text-2xl font-semibold text-slate-950">{{ layoutSummary.tableCapacity }}</p>
              </div>
              <div class="rounded-[1.3rem] border border-slate-200 bg-slate-50 px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">VIP</p>
                <p class="mt-2 text-2xl font-semibold text-slate-950">{{ layoutSummary.vipCount }}</p>
              </div>
              <div class="rounded-[1.3rem] border border-slate-200 bg-slate-50 px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Dancefloor</p>
                <p class="mt-2 text-2xl font-semibold text-slate-950">{{ layoutSummary.dancefloorCapacity }}</p>
              </div>
              <div class="rounded-[1.3rem] border border-slate-200 bg-slate-50 px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Total</p>
                <p class="mt-2 text-2xl font-semibold text-slate-950">{{ layoutSummary.totalCapacity }}</p>
              </div>
            </div>

            <div class="mt-4 space-y-3 text-sm leading-6">
              <div
                class="rounded-[1.3rem] border px-4 py-3"
                :class="hasStage ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-slate-200 bg-slate-50 text-slate-700'"
              >
                {{ hasStage ? 'Сцена на месте: зал подойдет для классического выступления.' : 'Сцена не добавлена: это допустимо для ресторанов, иммерсивных форматов и залов без фронтальной сцены.' }}
              </div>

              <div
                class="rounded-[1.3rem] border px-4 py-3"
                :class="
                  layoutSummary.totalCapacity > 0
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
                    : 'border-amber-200 bg-amber-50 text-amber-900'
                "
              >
                {{
                  layoutSummary.totalCapacity > 0
                    ? 'Вместимость собрана: схема уже готова для следующего шага в booking-flow.'
                    : 'Добавь хотя бы одну продаваемую зону, чтобы зал можно было использовать дальше.'
                }}
              </div>
            </div>
          </section>
        </div>
      </aside>
    </section>
  </div>
</template>
