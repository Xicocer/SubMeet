import { bookingApi } from './axios'
import type { OrganizerBookingDashboardResponse } from '@/types/analytics'
import type {
  BookingMutationResponse,
  BookingPayload,
  LoyaltyAccountResponse,
  SessionAvailabilityResponse,
  TicketVerificationResponse,
  UserBooking,
} from '@/types/booking'
import type { PaginatedResponse } from '@/types/event'

export const getSessionAvailabilityRequest = async (sessionId: number) => {
  const { data } = await bookingApi.get<SessionAvailabilityResponse>(`/sessions/${sessionId}/availability`)
  return data
}

export const createBookingRequest = async (payload: BookingPayload) => {
  const { data } = await bookingApi.post<BookingMutationResponse>('/bookings', payload)
  return data
}

export const createPurchaseRequest = async (payload: BookingPayload) => {
  const { data } = await bookingApi.post<BookingMutationResponse>('/bookings/purchase', payload)
  return data
}

export const createGuestPurchaseRequest = async (payload: BookingPayload) => {
  const { data } = await bookingApi.post<BookingMutationResponse>('/bookings/guest-purchase', payload)
  return data
}

export const getGuestBookingRequest = async (bookingId: number, guestToken: string) => {
  const { data } = await bookingApi.get<UserBooking>(`/guest/bookings/${bookingId}`, {
    params: {
      token: guestToken,
    },
  })

  return data
}

export const refreshGuestBookingPaymentRequest = async (bookingId: number, guestToken: string) => {
  const { data } = await bookingApi.post<BookingMutationResponse>(
    `/guest/bookings/${bookingId}/refresh-payment`,
    { token: guestToken },
  )

  return data
}

export const cancelGuestBookingRequest = async (bookingId: number, guestToken: string) => {
  const { data } = await bookingApi.post<BookingMutationResponse>(
    `/guest/bookings/${bookingId}/cancel`,
    { token: guestToken },
  )

  return data
}

export const getMyBookingsRequest = async (perPage = 20) => {
  const { data } = await bookingApi.get<PaginatedResponse<UserBooking>>('/my/bookings', {
    params: {
      per_page: perPage,
    },
  })

  return data
}

export const getMyBookingRequest = async (bookingId: number) => {
  const { data } = await bookingApi.get<UserBooking>(`/my/bookings/${bookingId}`)
  return data
}

export const cancelBookingRequest = async (bookingId: number) => {
  const { data } = await bookingApi.post<BookingMutationResponse>(`/bookings/${bookingId}/cancel`)
  return data
}

export const payBookingRequest = async (bookingId: number) => {
  const { data } = await bookingApi.post<BookingMutationResponse>(`/bookings/${bookingId}/pay`)
  return data
}

export const refreshBookingPaymentRequest = async (bookingId: number) => {
  const { data } = await bookingApi.post<BookingMutationResponse>(`/bookings/${bookingId}/refresh-payment`)
  return data
}

export const downloadTicketRequest = async (bookingId: number) => {
  const { data, headers } = await bookingApi.get<Blob>(`/my/bookings/${bookingId}/ticket`, {
    responseType: 'blob',
  })

  return {
    blob: data,
    contentDisposition: headers['content-disposition'] as string | undefined,
  }
}

export const downloadGuestTicketRequest = async (bookingId: number, guestToken: string) => {
  const { data, headers } = await bookingApi.get<Blob>(`/guest/bookings/${bookingId}/ticket`, {
    params: {
      token: guestToken,
    },
    responseType: 'blob',
  })

  return {
    blob: data,
    contentDisposition: headers['content-disposition'] as string | undefined,
  }
}

export const getLoyaltyAccountRequest = async () => {
  const { data } = await bookingApi.get<LoyaltyAccountResponse>('/loyalty')
  return data
}

export const verifyTicketRequest = async (ticketCode: string) => {
  const { data } = await bookingApi.post<TicketVerificationResponse>('/organizer/tickets/verify', {
    ticket_code: ticketCode,
  })

  return data
}

export const getOrganizerBookingDashboardRequest = async () => {
  const { data } = await bookingApi.get<OrganizerBookingDashboardResponse>('/organizer/dashboard')
  return data
}
