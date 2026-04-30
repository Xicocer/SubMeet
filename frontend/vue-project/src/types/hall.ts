export type HallStatus = 'draft' | 'active' | 'archived'
export type EditableHallStatus = Extract<HallStatus, 'draft' | 'active'>
export type HallElementType = 'stage' | 'seat' | 'vip_seat' | 'dancefloor'

export interface HallCanvasSize {
  width: number
  height: number
}

export interface HallLevel {
  id: string
  name: string
  order?: number | null
}

export interface HallLayoutElement {
  id: string
  type: HallElementType
  label?: string | null
  level_id?: string | null
  row?: string | null
  number?: string | null
  capacity?: number | null
  x: number
  y: number
  width?: number | null
  height?: number | null
}

export interface HallLayout {
  canvas: HallCanvasSize
  levels: HallLevel[]
  elements: HallLayoutElement[]
}

export interface HallCapacities {
  seat: number
  vip: number
  dancefloor: number
  total: number
}

export interface HallLayoutMeta {
  levels_count: number
  elements_count: number
  has_dancefloor: boolean
}

export interface HallSummary {
  id: number
  name: string
  address: string | null
  description: string | null
  organizer_id: number
  status: HallStatus
  capacities: HallCapacities
  layout_meta: HallLayoutMeta
  created_at: string | null
  updated_at: string | null
}

export interface HallDetails extends HallSummary {
  layout: HallLayout
}

export interface HallFilters {
  status?: HallStatus
  search?: string
  page?: number
  per_page?: number
}

export interface HallPayload {
  name: string
  address: string
  description: string | null
  status: EditableHallStatus
  layout: HallLayout
}

export interface HallMutationResponse {
  message: string
  hall: HallDetails
}

export interface HallCanvasDropPayload {
  mode: 'create' | 'move'
  x: number
  y: number
  type?: HallElementType
  elementId?: string
}

export interface HallCanvasMovePayload {
  elementId: string
  x: number
  y: number
}

export interface HallCanvasSelectionPayload {
  elementId: string | null
  additive?: boolean
}

export interface HallCanvasResizePayload {
  elementId: string
  x: number
  y: number
  width: number
  height: number
}
