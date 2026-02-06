<template>
  <div class="flex flex-col items-center justify-center h-full w-full p-4 pointer-events-none select-none">
    <i 
      class="fa-solid fa-circle-notch fa-spin text-primary-500 transition-all"
      :class="sizeClass"
    ></i>
    <p v-if="label" class="mt-3 text-xs font-black uppercase tracking-widest text-primary-500/80 animate-pulse">
      {{ label }}
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  size: { type: String, default: 'large' }, // small, medium, large, xl
  label: { type: String, default: '' }
});

const sizeClass = computed(() => {
  switch(props.size) {
    case 'small': return 'text-xl';
    case 'medium': return 'text-2xl';
    case 'large': return 'text-4xl';
    case 'xl': return 'text-6xl';
    default: return 'text-4xl';
  }
});
</script>