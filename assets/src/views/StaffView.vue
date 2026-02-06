<template>
  <div class="flex flex-col h-full w-full bg-surface-950 text-surface-200 overflow-hidden relative">
    
    <div class="flex-1 overflow-hidden relative">
        <router-view v-slot="{ Component }">
            <transition name="fade" mode="out-in">
                <component :is="Component" />
            </transition>
        </router-view>
    </div>

    </div>
</template>

<script setup>
// REMOVIDO: import SharedBottomNav from '@/components/shared/SharedBottomNav.vue';
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>