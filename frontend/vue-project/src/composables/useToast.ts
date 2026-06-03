import { readonly, ref } from 'vue'

export type ToastKind = 'success' | 'error' | 'info' | 'warning'

export interface ToastMessage {
  id: number
  kind: ToastKind
  title: string
  message?: string
}

export interface ToastOptions {
  kind?: ToastKind
  title: string
  message?: string
  timeout?: number
}

const toasts = ref<ToastMessage[]>([])
const timers = new Map<number, number>()
let nextToastId = 1

const removeToast = (id: number) => {
  toasts.value = toasts.value.filter((toast) => toast.id !== id)

  const timer = timers.get(id)

  if (timer !== undefined) {
    window.clearTimeout(timer)
    timers.delete(id)
  }
}

const showToast = (options: ToastOptions | string) => {
  const normalizedOptions: ToastOptions = typeof options === 'string'
    ? { title: options }
    : options

  const toast: ToastMessage = {
    id: nextToastId,
    kind: normalizedOptions.kind ?? 'info',
    title: normalizedOptions.title,
    message: normalizedOptions.message,
  }

  nextToastId += 1
  toasts.value = [toast, ...toasts.value].slice(0, 5)

  const timeout = normalizedOptions.timeout ?? 4200

  if (timeout > 0) {
    timers.set(toast.id, window.setTimeout(() => removeToast(toast.id), timeout))
  }

  return toast.id
}

export const useToast = () => ({
  toasts: readonly(toasts),
  showToast,
  removeToast,
})
