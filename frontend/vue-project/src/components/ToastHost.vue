<script setup lang="ts">
import { useToast, type ToastKind } from '@/composables/useToast'

const { toasts, removeToast } = useToast()

const toastClasses = (kind: ToastKind) => {
  switch (kind) {
    case 'success':
      return 'border-emerald-200 bg-emerald-50 text-emerald-950'
    case 'error':
      return 'border-rose-200 bg-rose-50 text-rose-950'
    case 'warning':
      return 'border-amber-200 bg-amber-50 text-amber-950'
    default:
      return 'border-blue-100 bg-white text-slate-950'
  }
}

const dotClasses = (kind: ToastKind) => {
  switch (kind) {
    case 'success':
      return 'bg-emerald-500'
    case 'error':
      return 'bg-rose-500'
    case 'warning':
      return 'bg-amber-500'
    default:
      return 'bg-blue-600'
  }
}
</script>

<template>
  <Teleport to="body">
    <TransitionGroup
      name="toast-list"
      tag="div"
      class="fixed right-4 top-4 z-[120] flex w-[min(25rem,calc(100vw-2rem))] flex-col gap-3 sm:right-6 sm:top-6"
    >
      <article
        v-for="toast in toasts"
        :key="toast.id"
        class="flex items-start gap-3 rounded-[1.35rem] border px-4 py-4 shadow-[0_22px_70px_-42px_rgba(15,23,42,0.75)] backdrop-blur"
        :class="toastClasses(toast.kind)"
      >
        <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" :class="dotClasses(toast.kind)"></span>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold leading-5">{{ toast.title }}</p>
          <p v-if="toast.message" class="mt-1 text-sm leading-6 text-slate-600">{{ toast.message }}</p>
        </div>
        <button
          type="button"
          class="rounded-full px-2 py-1 text-sm font-semibold text-slate-400 transition hover:bg-white/70 hover:text-slate-900"
          aria-label="Закрыть уведомление"
          @click="removeToast(toast.id)"
        >
          x
        </button>
      </article>
    </TransitionGroup>
  </Teleport>
</template>

<style scoped>
.toast-list-enter-active,
.toast-list-leave-active {
  transition: opacity 180ms ease, transform 180ms ease;
}

.toast-list-enter-from,
.toast-list-leave-to {
  opacity: 0;
  transform: translateY(-0.75rem) scale(0.98);
}
</style>
