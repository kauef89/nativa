<template>
  <div 
    class="flex flex-col h-full bg-surface-1 rounded-[24px] transition-all duration-500 ease-[cubic-bezier(0.2,0,0,1)] z-30 relative overflow-hidden shadow-2xl"
    :class="mini ? 'w-20 items-center' : 'w-64'"
    @mouseenter="mini = false"
    @mouseleave="mini = true"
  >
    <div class="h-20 flex items-center justify-center shrink-0 w-full relative mb-1 bg-gradient-to-b from-surface-1 to-transparent">
        <transition name="fade-logo">
            <img v-if="mini" :src="logoMono" class="w-8 h-8 object-contain absolute opacity-80" />
        </transition>
        <transition name="fade-logo">
            <img v-if="!mini" :src="logoFull" class="h-6 w-auto object-contain absolute" />
        </transition>
    </div>

    <div class="flex-1 overflow-y-auto scrollbar-hide w-full py-1 space-y-4" 
         :class="mini ? 'px-0 items-center flex flex-col' : 'px-3'">
        
        <div class="space-y-0.5 w-full">
            <div class="text-[9px] font-black text-primary-500 uppercase tracking-widest mb-1 pl-3 transition-opacity duration-300" 
                 :class="mini ? 'opacity-0 h-0 overflow-hidden' : 'opacity-100'">
                Operação
            </div>
            
            <router-link 
                v-for="item in operationItems" :key="item.key" :to="{ name: item.route }" custom v-slot="{ navigate, isActive }"
            >
                <div @click="navigate" class="nav-item group relative" :class="{ 'active': isActive, 'justify-center': mini, '!w-10 !mx-auto': mini, 'w-full': !mini }">
                    <div class="icon-container" :class="isActive ? 'text-surface-950' : 'text-surface-400 group-hover:text-surface-200'">
                        <i :class="item.icon" class="text-lg transition-transform duration-300 group-hover:scale-110"></i>
                        <div v-if="item.badge > 0" class="absolute -top-1 -right-1 min-w-[16px] h-[16px] rounded-full flex items-center justify-center text-[8px] font-black text-white shadow-md border-2 border-surface-1 animate-pulse" :class="item.badgeColor || 'bg-red-500'">
                            {{ item.badge }}
                        </div>
                    </div>
                    <span v-if="!mini" class="nav-label flex-1">{{ item.label }}</span>
                    <span v-if="!mini && item.badge > 0" class="mr-2 px-1.5 py-0.5 rounded-full text-[9px] font-black text-surface-950" :class="item.badgeColor || 'bg-red-500'">{{ item.badge }}</span>
                    <div v-if="isActive && !mini" class="absolute right-3 w-1.5 h-1.5 rounded-full bg-primary-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></div>
                </div>
            </router-link>
        </div>

        <div class="space-y-0.5 w-full" v-if="extraTools.length > 0">
             <div class="text-[9px] font-black text-primary-500 uppercase tracking-widest mb-1 pl-3 transition-opacity duration-300" 
                 :class="mini ? 'opacity-0 h-0 overflow-hidden' : 'opacity-100'">
                Gestão
            </div>
            <template v-for="tool in extraTools" :key="tool.route">
                <router-link :to="{ name: tool.route }" custom v-slot="{ navigate, isActive }">
                    <div @click="navigate" class="nav-item group" :class="{ 'active': isActive, 'justify-center': mini, '!w-10 !mx-auto': mini, 'w-full': !mini }">
                        <div class="icon-container" :class="isActive ? 'text-surface-950' : 'text-surface-400 group-hover:text-surface-200'">
                            <i :class="tool.icon" class="text-lg transition-transform duration-300 group-hover:scale-110"></i>
                        </div>
                        <span v-if="!mini" class="nav-label">{{ tool.label }}</span>
                        <div v-if="isActive && !mini" class="absolute right-3 w-1.5 h-1.5 rounded-full bg-primary-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></div>
                    </div>
                </router-link>
            </template>
        </div>

    </div>

    <div class="p-2 mt-auto bg-gradient-to-t from-surface-1 to-transparent w-full">
        <div class="flex items-center gap-3 cursor-pointer p-1.5 rounded-2xl transition-all hover:bg-surface-2" 
             :class="{ 'justify-center': mini }"
             @click="handleLogout">
            <div class="w-8 h-8 rounded-[10px] bg-surface-2 text-surface-400 flex items-center justify-center font-bold shrink-0 shadow-inner text-xs">
                {{ userStore.user?.name?.charAt(0).toUpperCase() || 'U' }}
            </div>
            <div v-if="!mini" class="flex flex-col min-w-0 pr-2">
                <span class="text-xs font-bold text-surface-200 truncate">{{ userStore.user?.name?.split(' ')[0] }}</span>
                <span class="text-[8px] text-surface-500 truncate font-bold">Sair do sistema</span>
            </div>
            <i v-if="!mini" class="fa-solid fa-right-from-bracket text-red-400/70 text-[10px] ml-auto hover:text-red-400 transition-colors"></i>
        </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useUserStore } from '@/stores/user-store';
import { useNavigation } from '@/composables/useNavigation';
import logoMono from '@/svg/nativa-mono.svg';
import logoFull from '@/svg/logo-branca.svg';

const userStore = useUserStore();
const { operationItems, extraTools } = useNavigation();
const mini = ref(true);

const handleLogout = () => {
    if(confirm('Sair do sistema?')) userStore.logout();
};
</script>

<style scoped>
.nav-item { @apply flex items-center p-0 rounded-full transition-all duration-300 cursor-pointer relative overflow-visible mb-0.5 mx-auto h-10 hover:bg-surface-2; }
.nav-item.active { @apply bg-primary-400 shadow-inner; }
.icon-container { @apply w-10 h-10 flex items-center justify-center rounded-full transition-colors shrink-0 relative; }
.nav-label { @apply ml-2 whitespace-nowrap font-bold text-xs tracking-wide text-surface-200 transition-colors; }   
.nav-item.active .nav-label { @apply text-surface-900; }
.fade-logo-enter-active, .fade-logo-leave-active { transition: opacity 0.3s ease; }
.fade-logo-enter-from, .fade-logo-leave-to { opacity: 0; }
</style>