<script setup lang="ts">
const props = withDefaults(defineProps<{
  active: boolean
  loading?: boolean
  compact?: boolean
}>(), {
  loading: false,
  compact: false,
})

defineEmits<{
  toggle: []
}>()
</script>

<template>
  <button
    type="button"
    class="inline-flex items-center justify-center gap-2 rounded-full border text-sm font-semibold transition duration-200"
    :class="[
      compact ? 'px-3 py-2' : 'px-4 py-3',
      active
        ? 'border-blue-200 bg-blue-50 text-blue-700 hover:border-blue-300 hover:bg-blue-100'
        : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50',
    ]"
    :disabled="loading"
    @click="$emit('toggle')"
  >
    <span class="text-base leading-none">{{ active ? '★' : '☆' }}</span>
    <span>
      {{ loading ? 'Сохраняем...' : active ? 'В списке' : 'Хочу сходить' }}
    </span>
  </button>
</template>
