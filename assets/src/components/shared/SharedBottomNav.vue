<template>
  <div>
    <div 
      v-if="shouldShow"
      class="fixed bottom-4 h-16 bg-surface-2/95 backdrop-blur-xl border border-surface-3/30 rounded-full flex items-center z-40 shadow-[0_8px_30px_rgba(0,0,0,0.4)] safe-area-bottom transition-all duration-300 ease-[cubic-bezier(0.175,0.885,0.32,1.275)] overflow-hidden"
      :class="containerClasses"
      @touchstart="handleTouchStart"
      @touchend="handleTouchEnd"
      @click="handleContainerClick"
    >
      
      <transition name="fade-slide">
        <div v-if="!isCollapsed" class="flex items-center justify-evenly w-full h-full">
          <button 
            v-for="item in navItems" 
            :key="item.key"
            @click.stop="onClick(item)"
            class="relative flex flex-col items-center justify-center w-14 h-full transition-all duration-300 group"
            :class="isActive(item) ? 'text-primary-500' : 'text-surface-400 hover:text-surface-200'"
          >
            <div 
              class="flex items-center justify-center w-10 h-10 rounded-full transition-all duration-300"
              :class="[
                isActive(item) ? 'bg-primary-500/10 shadow-[0_0_15px_rgba(var(--primary-500-rgb),0.3)]' : 'bg-transparent',
                item.highlight ? 'bg-surface-3 border border-surface-4/50 text-surface-200' : ''
              ]"
            >
              <i :class="[item.icon, isActive(item) ? 'text-xl' : 'text-lg']"></i>
            </div>

            <span v-if="item.badge" class="absolute top-3 right-3 bg-red-500 text-white text-[9px] font-black w-4 h-4 flex items-center justify-center rounded-full border-2 border-surface-2 animate-bounce">
              {{ item.badge }}
            </span>
          </button>
        </div>
      </transition>

      <transition name="scale-in">
        <div v-if="isCollapsed" class="absolute inset-0 flex items-center justify-center cursor-pointer">
            <i class="fa-solid fa-angles-left text-primary-500 text-xl animate-pulse"></i>
            
            <span v-if="hasHiddenBadges" class="absolute top-3 right-3 w-3 h-3 bg-red-500 rounded-full border-2 border-surface-2"></span>
        </div>
      </transition>

    </div>

    <BottomSheet 
      v-model:visible="isMenuOpen"
      title="Menu Gerencial"
    >
      <div class="p-4 grid grid-cols-4 gap-4 pb-8">
        <button 
            v-for="tool in extraTools" 
            :key="tool.route"
            class="flex flex-col items-center gap-2 p-2 active:scale-95 transition-transform"
            @click="navigate(tool.route)"
        >
            <div class="w-12 h-12 rounded-[18px] bg-surface-2 border border-surface-3/20 flex items-center justify-center text-primary-400 shadow-sm">
                <i :class="tool.icon" class="text-xl"></i>
            </div>
            <span class="text-[10px] font-bold text-surface-300 text-center leading-tight">{{ tool.label }}</span>
        </button>
      </div>
    </BottomSheet>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useUserStore } from '@/stores/user-store';
import { useCartStore } from '@/stores/cart-store';
import { useNavigation } from '@/composables/useNavigation';
import BottomSheet from '@/components/shared/BottomSheet.vue';

const emit = defineEmits(['trigger', 'action', 'update:activeTab']);
const router = useRouter();
const route = useRoute();
const userStore = useUserStore();
const cartStore = useCartStore();

const { navItems, extraTools, isMenuOpen, handleAction } = useNavigation();

// --- ESTADO DO COLAPSO ---
const isCollapsed = ref(false);
const touchStartX = ref(0);
const SWIPE_THRESHOLD = 50; // Pixels para considerar um swipe

const isStaffArea = computed(() => route.path.startsWith('/staff'));

const shouldShow = computed(() => {
    if (route.meta.hideNav) return false;
    return isStaffArea.value ? userStore.isStaff : true;
});

// Classes dinâmicas para animar a largura e posição
const containerClasses = computed(() => {
    if (isCollapsed.value) {
        // Recolhido: Encostado na direita, largura pequena
        return 'right-4 w-16 justify-center px-0 hover:bg-surface-2 cursor-pointer'; 
    }
    // Expandido: Ocupa a largura toda (com margens)
    return 'left-4 right-4 justify-evenly';
});

// Verifica se tem algum badge importante escondido ao colapsar
const hasHiddenBadges = computed(() => {
    return navItems.value.some(item => item.badge > 0);
});

// --- LÓGICA DE TOQUE (SWIPE) ---

const handleTouchStart = (e) => {
    touchStartX.value = e.changedTouches[0].screenX;
};

const handleTouchEnd = (e) => {
    const touchEndX = e.changedTouches[0].screenX;
    const diff = touchEndX - touchStartX.value;

    // Se arrastou da esquerda para direita (valor positivo) e foi maior que o limite
    if (!isCollapsed.value && diff > SWIPE_THRESHOLD) {
        isCollapsed.value = true;
    }
};

const handleContainerClick = () => {
    // Se estiver recolhido, qualquer clique expande
    if (isCollapsed.value) {
        isCollapsed.value = false;
    }
};

// --- LÓGICA DE NAVEGAÇÃO ORIGINAL ---

const isActive = (item) => {
    if (item.key === 'account' && route.query.tab === 'profile') return true;
    if (item.route && route.name === item.route && route.query.tab !== 'profile') return true;
    if (item.key === 'cart' && cartStore.isOpen) return true;
    if (item.key === 'menu_drawer' && isMenuOpen.value) return true;
    return false;
};

const onClick = (item) => {
    // Se clicar em um item, não queremos colapsar, apenas executar a ação
    if (item.action === 'triggerNotifications') {
        emit('trigger', 'notifications');
    }
    else {
        handleAction(item, emit);
    }
};

const navigate = (routeName) => {
    isMenuOpen.value = false;
    router.push({ name: routeName });
};
</script>

<style scoped>
.safe-area-bottom { padding-bottom: env(safe-area-inset-bottom, 20px); }

.animate-slide-up { animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes slideUp { from { transform: translateY(100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

/* Transições de conteúdo */
.fade-slide-enter-active { transition: all 0.3s ease-out; transition-delay: 0.1s; }
.fade-slide-leave-active { transition: all 0.1s ease-in; position: absolute; opacity: 0; }
.fade-slide-enter-from { opacity: 0; transform: translateX(-10px); }
.fade-slide-leave-to { opacity: 0; transform: translateX(10px); }

.scale-in-enter-active { transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); transition-delay: 0.1s; }
.scale-in-leave-active { transition: all 0.1s ease; position: absolute; }
.scale-in-enter-from { opacity: 0; transform: scale(0); }
.scale-in-leave-to { opacity: 0; transform: scale(0); }
</style>