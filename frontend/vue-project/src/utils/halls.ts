import type {
  HallCanvasSize,
  HallElementType,
  HallLayout,
  HallLayoutElement,
  HallLayoutMeta,
  HallLevel,
} from '@/types/hall'

export const DEFAULT_CANVAS_SIZE: HallCanvasSize = {
  width: 960,
  height: 640,
}

const DEFAULT_ELEMENT_SIZES: Record<HallElementType, { width: number; height: number }> = {
  stage: { width: 280, height: 96 },
  seat: { width: 42, height: 42 },
  vip_seat: { width: 52, height: 52 },
  dancefloor: { width: 260, height: 170 },
}

export const snapToGrid = (value: number, gridSize = 10) => Math.round(value / gridSize) * gridSize

export const clampToRange = (value: number, min: number, max: number) => {
  return Math.min(Math.max(value, min), max)
}

const createId = (prefix: string) => {
  const randomPart = Math.random().toString(36).slice(2, 7)
  return `${prefix}-${Date.now().toString(36)}-${randomPart}`
}

export const createHallLevel = (name = 'Партер', order = 1): HallLevel => ({
  id: createId('level'),
  name,
  order,
})

export const getDefaultElementSize = (type: HallElementType) => {
  return DEFAULT_ELEMENT_SIZES[type]
}

export const createHallElement = (
  type: HallElementType,
  overrides: Partial<HallLayoutElement> = {},
): HallLayoutElement => {
  const size = getDefaultElementSize(type)

  const base: HallLayoutElement = {
    id: createId(type),
    type,
    label:
      type === 'stage'
        ? 'Главная сцена'
        : type === 'dancefloor'
          ? 'Танцпол'
          : type === 'vip_seat'
            ? 'VIP'
            : 'Место',
    x: 120,
    y: 120,
    width: size.width,
    height: size.height,
  }

  if (type === 'seat') {
    base.row = 'A'
    base.number = '1'
    base.label = 'A-1'
  }

  if (type === 'vip_seat') {
    base.row = 'VIP'
    base.number = '1'
    base.label = 'VIP-1'
  }

  if (type === 'dancefloor') {
    base.capacity = 80
  }

  return {
    ...base,
    ...overrides,
  }
}

export const createDefaultHallLayout = (): HallLayout => ({
  canvas: { ...DEFAULT_CANVAS_SIZE },
  levels: [
    {
      id: 'parter',
      name: 'Партер',
      order: 1,
    },
  ],
  elements: [
    createHallElement('stage', {
      id: 'stage-main',
      x: 340,
      y: 48,
      width: 280,
      height: 96,
      label: 'Главная сцена',
    }),
  ],
})

const normalizeElement = (element: HallLayoutElement): HallLayoutElement => {
  const size = getDefaultElementSize(element.type)

  return {
    ...element,
    label: element.label ?? null,
    level_id: element.level_id ?? null,
    row: element.row ?? null,
    number: element.number ?? null,
    capacity: element.capacity ?? null,
    width: element.width ?? size.width,
    height: element.height ?? size.height,
  }
}

export const normalizeHallLayout = (layout?: HallLayout | null): HallLayout => {
  const normalizedLevels = [...(layout?.levels ?? [])]
    .map((level, index) => ({
      ...level,
      order: level.order ?? index + 1,
    }))
    .sort((left, right) => (left.order ?? 0) - (right.order ?? 0))

  return {
    canvas: {
      width: layout?.canvas?.width ?? DEFAULT_CANVAS_SIZE.width,
      height: layout?.canvas?.height ?? DEFAULT_CANVAS_SIZE.height,
    },
    levels: normalizedLevels.length > 0 ? normalizedLevels : [createHallLevel()],
    elements: (layout?.elements ?? []).map(normalizeElement),
  }
}

export const cloneHallLayout = (layout: HallLayout): HallLayout => {
  return normalizeHallLayout(JSON.parse(JSON.stringify(layout)) as HallLayout)
}

export const getElementLabel = (element: HallLayoutElement) => {
  if (element.type === 'seat' || element.type === 'vip_seat') {
    return element.label || [element.row, element.number].filter(Boolean).join('-') || 'Место'
  }

  if (element.type === 'dancefloor') {
    return element.label || `Танцпол · ${element.capacity ?? 0}`
  }

  return element.label || 'Сцена'
}

export const summarizeHallLayout = (
  layout: HallLayout,
): {
  seatCount: number
  vipCount: number
  dancefloorCapacity: number
  totalCapacity: number
  hasStage: boolean
  meta: HallLayoutMeta
} => {
  let seatCount = 0
  let vipCount = 0
  let dancefloorCapacity = 0
  let hasStage = false

  for (const element of layout.elements) {
    if (element.type === 'stage') {
      hasStage = true
    }

    if (element.type === 'seat') {
      seatCount += 1
    }

    if (element.type === 'vip_seat') {
      vipCount += 1
    }

    if (element.type === 'dancefloor') {
      dancefloorCapacity += Number(element.capacity ?? 0)
    }
  }

  return {
    seatCount,
    vipCount,
    dancefloorCapacity,
    totalCapacity: seatCount + vipCount + dancefloorCapacity,
    hasStage,
    meta: {
      levels_count: layout.levels?.length ?? 0,
      elements_count: layout.elements.length,
      has_dancefloor: layout.elements.some((element) => element.type === 'dancefloor'),
    },
  }
}
