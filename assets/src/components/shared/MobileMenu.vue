<template>
  <div class="flex flex-col h-full w-full max-w-[100vw] bg-surface-base overflow-hidden">
    
    <div class="bg-surface-1 pt-8 pb-6 px-5 rounded-b-[32px] shadow-2xl z-30 shrink-0 border-b border-surface-3/10 relative">
        
        <div class="flex justify-between items-center mb-5">
            <div class="flex items-center gap-4 flex-1 min-w-0">
                
                <div class="w-12 h-12 rounded-full bg-surface-2 flex items-center justify-center text-primary-500 border border-primary-500/20 shadow-lg overflow-hidden shrink-0">
                    <img v-if="currentAvatar" :src="currentAvatar" class="w-full h-full object-cover" />
                    <span v-else-if="currentName" class="font-black text-lg">{{ currentName.charAt(0).toUpperCase() }}</span>
                    <i v-else class="fa-solid fa-user text-lg"></i>
                </div>

                <div v-if="!isWaiterView" class="flex flex-col">
                    <h1 class="text-surface-0 font-black text-lg leading-tight truncate">
                        Olá, {{ firstName }}
                    </h1>
                    <p class="text-surface-400 text-xs font-bold tracking-wide">O que vamos comer hoje?</p>
                </div>

                <div v-else class="flex flex-col flex-1 min-w-0">
                    <button class="text-left group" @click="$emit('open-accounts')">
                        <h2 class="text-surface-0 font-black text-sm leading-none flex items-center gap-2 truncate group-active:opacity-70 transition-opacity">
                            Mesa {{ sessionStore.identifier }} 
                            <i class="fa-solid fa-chevron-down text-[10px] text-primary-500"></i>
                        </h2>
                        <div class="text-[10px] uppercase font-bold text-primary-400 tracking-wider mt-0.5 truncate">
                            {{ sessionStore.currentAccount }}
                        </div>
                    </button>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                
                <template v-if="isWaiterView">
                    <button 
                        class="w-10 h-10 rounded-full bg-surface-2 text-surface-400 flex items-center justify-center hover:text-surface-0 hover:bg-surface-3 transition-colors shadow-sm border border-surface-3/10"
                        @click="$emit('open-report')"
                        v-tooltip.bottom="'Resumo'"
                    >
                        <i class="fa-solid fa-list-ol text-sm"></i>
                    </button>
                    
                    <button 
                        class="w-10 h-10 rounded-full bg-surface-2 text-surface-400 flex items-center justify-center hover:text-surface-0 hover:bg-surface-3 transition-colors shadow-sm border border-surface-3/10 relative"
                        @click.stop="cartStore.isOpen = true"
                    >
                        <i class="fa-solid fa-cart-shopping text-sm"></i>
                        <span v-if="cartStore.totalItems > 0" class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border border-surface-2 animate-bounce"></span>
                    </button>
                </template>

                <button 
                    class="w-10 h-10 rounded-full bg-surface-2 text-surface-400 flex items-center justify-center hover:text-surface-0 hover:bg-surface-3 transition-colors shadow-sm border border-surface-3/10"
                    @click="showCategories = true"
                >
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
            </div>
        </div>

        <div class="relative group">
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-surface-500 group-focus-within:text-primary-500 transition-colors"></i>
            <input 
                v-model="localSearchTerm"
                type="text" 
                placeholder="Buscar pratos, ingredientes..." 
                class="w-full h-12 bg-surface-base border border-surface-3/20 rounded-full pl-11 pr-4 text-surface-0 placeholder-surface-500 focus:border-primary-500 focus:outline-none transition-all font-bold shadow-inner text-sm"
            >
        </div>
    </div>

    <div class="flex-1 overflow-y-auto scrollbar-hide pb-32 scroll-smooth bg-surface-base w-full" ref="scrollContainer">
        <div class="p-5 space-y-10 animate-fade-in min-h-full">
            
            <div v-if="menuStore.isLoading" class="flex flex-col items-center justify-center pt-20 text-primary-500">
                <LoadingSpinner size="large" label="Carregando..." />
            </div>

            <template v-else>
                <div v-for="cat in visibleCategories" :key="cat.id" :id="'cat-' + cat.id" class="category-section scroll-mt-60">
                    
                    <h3 class="font-black text-surface-0 text-lg tracking-tight uppercase flex items-center gap-3 mb-4 pt-2">
                        <span class="w-1.5 h-5 bg-primary-500 rounded-full shadow-[0_0_10px_rgba(6,182,212,0.8)]"></span> {{ cat.name }}
                    </h3>

                    <div v-if="cat.products && cat.products.length > 0" class="grid grid-cols-1 gap-4 mb-6">
                        <div v-for="product in cat.products" :key="product.id"
                             class="group bg-surface-1 border border-surface-3/10 rounded-[20px] p-3 flex gap-4 cursor-pointer active:bg-surface-2 transition-all shadow-sm hover:border-primary-500/30"
                             @click="$emit('open-product', product)"
                        >
                            <div class="w-24 h-24 rounded-[16px] bg-surface-2 shrink-0 overflow-hidden relative border border-surface-3/10 shadow-inner">
                                <img v-if="product.image" :src="product.image" class="w-full h-full object-cover">
                                <div v-else class="flex items-center justify-center h-full text-surface-600"><i class="fa-regular fa-image text-2xl"></i></div>
                                <div v-if="product.old_price" class="absolute top-0 left-0 bg-red-600 text-surface-0 text-[9px] font-black px-2 py-1 rounded-br-xl z-10 shadow-md">OFERTA</div>
                            </div>

                            <div class="flex-1 flex flex-col justify-between py-1 min-w-0">
                                <div>
                                    <div class="font-black text-surface-0 text-base leading-tight mb-1 line-clamp-2">{{ product.name }}</div>
                                    <div class="text-[11px] text-surface-400 line-clamp-2 leading-relaxed font-medium">{{ product.description }}</div>
                                </div>
                                <div class="flex justify-between items-end mt-2">
                                    <div class="flex flex-col">
                                        <span v-if="product.old_price" class="text-[10px] text-surface-500 line-through font-bold">{{ formatCurrency(product.old_price) }}</span>
                                        <span class="text-primary-400 font-black text-lg">{{ formatCurrency(product.price) }}</span>
                                    </div>
                                    <div class="w-8 h-8 rounded-full bg-surface-2 border border-surface-3/20 flex items-center justify-center text-primary-500 group-active:bg-primary-500 group-active:text-surface-950 transition-colors shadow-sm">
                                        <i class="fa-solid fa-plus text-xs font-black"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="cat.children && cat.children.length > 0" class="flex flex-col gap-6">
                        <div v-for="child in cat.children" :key="child.id">
                            
                            <h4 class="font-bold text-surface-300 text-sm uppercase tracking-wide mb-3 pl-2 border-l-2 border-primary-500/30 flex items-center gap-2">
                                {{ child.name }}
                            </h4>

                            <div class="grid grid-cols-1 gap-4">
                                <div v-for="product in child.products" :key="product.id"
                                     class="group bg-surface-1 border border-surface-3/10 rounded-[20px] p-3 flex gap-4 cursor-pointer active:bg-surface-2 transition-all shadow-sm hover:border-primary-500/30"
                                     @click="$emit('open-product', product)"
                                >
                                    <div class="w-24 h-24 rounded-[16px] bg-surface-2 shrink-0 overflow-hidden relative border border-surface-3/10 shadow-inner">
                                        <img v-if="product.image" :src="product.image" class="w-full h-full object-cover">
                                        <div v-else class="flex items-center justify-center h-full text-surface-600"><i class="fa-regular fa-image text-2xl"></i></div>
                                        <div v-if="product.old_price" class="absolute top-0 left-0 bg-red-600 text-surface-0 text-[9px] font-black px-2 py-1 rounded-br-xl z-10 shadow-md">OFERTA</div>
                                    </div>

                                    <div class="flex-1 flex flex-col justify-between py-1 min-w-0">
                                        <div>
                                            <div class="font-black text-surface-0 text-base leading-tight mb-1 line-clamp-2">{{ product.name }}</div>
                                            <div class="text-[11px] text-surface-400 line-clamp-2 leading-relaxed font-medium">{{ product.description }}</div>
                                        </div>
                                        <div class="flex justify-between items-end mt-2">
                                            <div class="flex flex-col">
                                                <span v-if="product.old_price" class="text-[10px] text-surface-500 line-through font-bold">{{ formatCurrency(product.old_price) }}</span>
                                                <span class="text-primary-400 font-black text-lg">{{ formatCurrency(product.price) }}</span>
                                            </div>
                                            <div class="w-8 h-8 rounded-full bg-surface-2 border border-surface-3/20 flex items-center justify-center text-primary-500 group-active:bg-primary-500 group-active:text-surface-950 transition-colors shadow-sm">
                                                <i class="fa-solid fa-plus text-xs font-black"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <BottomSheet 
        v-model:visible="showCategories" 
        title="Navegar"
        subtitle="Selecione uma categoria"
    >
        <div class="flex flex-wrap justify-center gap-2 pb-8 px-2">
            <button 
                v-for="cat in visibleCategories" 
                :key="cat.id"
                class="px-5 py-2.5 rounded-full bg-surface-2 hover:bg-surface-3 text-surface-200 font-bold text-xs uppercase tracking-wide border border-surface-3/30 hover:border-surface-400 active:scale-95 transition-all shadow-sm"
                @click="jumpToCategory(cat.id)"
            >
                {{ cat.name }}
            </button>
        </div>
    </BottomSheet>

  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { useMenuLogic } from '@/composables/useMenuLogic'; 
import { useFormat } from '@/composables/useFormat';
import { useUserStore } from '@/stores/user-store';
import { useSessionStore } from '@/stores/session-store';
import { useCartStore } from '@/stores/cart-store';
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue';
import BottomSheet from '@/components/shared/BottomSheet.vue';
import md5 from 'blueimp-md5'; 

const props = defineProps({
    externalSearch: { type: String, default: '' },
    isWaiterView: { type: Boolean, default: false }
});

const emit = defineEmits(['open-product', 'open-accounts', 'open-report']);

const userStore = useUserStore();
const sessionStore = useSessionStore();
const cartStore = useCartStore();
const { formatCurrency } = useFormat(); 

const localSearchTerm = ref('');
const showCategories = ref(false);

const { 
    menuStore, 
    visibleCategories, 
    activeCat, 
    scrollContainer, 
    pillsContainer, 
    scrollToCategory 
} = useMenuLogic(localSearchTerm);

watch(() => props.externalSearch, (val) => { localSearchTerm.value = val; });

// --- LÓGICA DE AVATAR E NOME ---
const currentUser = computed(() => userStore.user);

const currentName = computed(() => {
    return currentUser.value?.name || 'Visitante';
});

const firstName = computed(() => {
    return currentName.value.split(' ')[0];
});

const currentAvatar = computed(() => {
    if (currentUser.value?.avatar_url) return currentUser.value.avatar_url;
    if (currentUser.value?.email) {
        try {
            const hash = md5(currentUser.value.email.trim().toLowerCase());
            return `https://www.gravatar.com/avatar/${hash}?d=mp`;
        } catch (e) { return null; }
    }
    return null;
});

const jumpToCategory = (id) => {
    showCategories.value = false;
    setTimeout(() => scrollToCategory(id), 300);
};
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.animate-fade-in { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>