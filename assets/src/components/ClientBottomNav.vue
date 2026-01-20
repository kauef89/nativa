<template>
  <div class="fixed bottom-6 left-1/2 -translate-x-1/2 w-[calc(100%-32px)] max-w-md z-50">
    
    <div class="glass-pill flex justify-between items-center px-2 h-[72px] relative">
        
        <button 
            v-for="item in items" 
            :key="item.id"
            class="group relative flex-1 h-full flex flex-col items-center justify-center gap-1 rounded-full transition-all duration-300"
            @click="selectTab(item)"
        >
            <div 
                v-if="activeTab === item.id" 
                class="absolute inset-2 bg-white/10 rounded-full blur-md -z-10 transition-all duration-500"
            ></div>

            <div class="relative transition-transform duration-300 group-active:scale-90"
                 :class="activeTab === item.id ? '-translate-y-1' : ''">
                
                <i class="text-xl transition-colors duration-300" 
                   :class="[
                       item.icon, 
                       activeTab === item.id ? 'text-primary-400 drop-shadow-[0_0_8px_rgba(251,191,36,0.5)]' : 'text-surface-400 group-hover:text-surface-200'
                   ]"
                ></i>
                
                <span v-if="item.id === 'cart' && cartCount > 0" 
                      class="absolute -top-2 -right-2 bg-red-500 text-white text-[10px] font-bold h-4 min-w-[16px] px-1 rounded-full flex items-center justify-center border border-black/50 shadow-lg animate-bounce z-20">
                    {{ cartCount }}
                </span>
            </div>
            
            <span 
                class="text-[9px] font-bold tracking-wide transition-all duration-300 absolute bottom-3"
                :class="activeTab === item.id ? 'opacity-100 translate-y-0 text-white' : 'opacity-0 translate-y-2 text-surface-500'"
            >
                {{ item.label }}
            </span>

            <span 
                v-if="activeTab !== item.id" 
                class="absolute bottom-3 w-1 h-1 rounded-full bg-surface-600 opacity-0 group-hover:opacity-100 transition-opacity"
            ></span>
        </button>

    </div>

  </div>
</template>

<script setup>
const props = defineProps(['activeTab', 'cartCount']);
const emit = defineEmits(['update:activeTab', 'trigger']);

const items = [
    { id: 'home', label: 'Início', icon: 'fa-solid fa-house' },
    { id: 'menu', label: 'Cardápio', icon: 'fa-solid fa-burger' },
    { id: 'cart', label: 'Carrinho', icon: 'fa-solid fa-bag-shopping' },
    { id: 'contact', label: 'Contato', icon: 'fa-solid fa-comment-dots' },
    { id: 'profile', label: 'Conta', icon: 'fa-solid fa-user' }
];

const selectTab = (item) => {
    // Ações especiais que não mudam a navegação principal
    if (item.id === 'cart' || item.id === 'contact') {
        emit('trigger', item.id);
    } else {
        emit('update:activeTab', item.id);
    }
};
</script>

<style scoped>
.glass-pill {
    /* Efeito Liquid Glass: Fundo preto translúcido, Blur forte, Borda fina, Sombra profunda */
    @apply bg-surface-950/80 backdrop-blur-xl border border-white/10 rounded-full shadow-[0_8px_32px_rgba(0,0,0,0.5)];
}
</style>