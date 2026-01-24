<template>
  <div class="flex flex-col h-full bg-surface-950">
    
    <div class="w-full py-3 bg-surface-950 border-b border-surface-800 shadow-sm shrink-0 z-30">
        <div class="flex gap-2 overflow-x-auto scrollbar-hide items-center px-4 w-full" ref="pillsContainer">
            <button 
                v-for="cat in visibleCategories" 
                :key="cat.id"
                :id="'pill-' + cat.id"
                class="px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap transition-all border snap-center"
                :class="activeCat === cat.id 
                    ? 'bg-primary-500 text-surface-900 border-primary-500 shadow-sm scale-105' 
                    : 'bg-surface-900 text-surface-400 border-surface-800 hover:bg-surface-800 hover:text-white'"
                @click="scrollToCategory(cat.id)"
            >
                {{ cat.name }}
            </button>
            <div class="w-4 shrink-0"></div>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto scrollbar-hide pb-32 scroll-smooth" ref="scrollContainer">
        
        <div class="p-4 pb-2 animate-fade-in">
            <div class="relative group">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-surface-500 group-focus-within:text-primary-500 transition-colors"></i>
                <input 
                    v-model="searchTerm"
                    type="text" 
                    placeholder="Buscar no cardápio..." 
                    class="w-full bg-surface-900 border border-surface-800 text-white pl-11 pr-4 h-12 rounded-xl focus:outline-none focus:border-primary-500 transition-all shadow-sm text-sm placeholder:text-surface-600"
                />
            </div>
        </div>

        <div class="p-4 space-y-10 animate-fade-in">
            
            <div v-if="menuStore.isLoading" class="flex flex-col items-center justify-center p-20 text-primary-500">
                <i class="fa-solid fa-spinner fa-spin text-4xl mb-4"></i>
                <p class="text-sm font-bold uppercase tracking-wider animate-pulse">Carregando cardápio...</p>
            </div>

            <template v-else>
                <div v-for="cat in visibleCategories" :key="cat.id" :id="'cat-' + cat.id" class="category-section scroll-mt-36">
                    
                    <div class="flex flex-col items-center gap-3 mb-5 pt-2">
                        <div v-if="cat.image" class="w-full h-28 rounded-2xl overflow-hidden relative shadow-lg mb-1 border border-surface-800">
                            <img :src="cat.image" class="w-full h-full object-cover opacity-80">
                            <div class="absolute inset-0 bg-gradient-to-t from-surface-950 via-transparent to-transparent"></div>
                            <h3 class="absolute bottom-3 left-4 font-black text-white text-xl tracking-tight uppercase drop-shadow-md">{{ cat.name }}</h3>
                        </div>
                        <div v-else class="flex flex-col items-center gap-2">
                            <h3 class="font-black text-white text-xl tracking-tight text-center uppercase">{{ cat.name }}</h3>
                            <div class="w-8 h-1 bg-primary-500 rounded-full"></div>
                        </div>
                    </div>

                    <div v-if="cat.products && cat.products.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 mb-6">
                        <div v-for="product in cat.products" :key="product.id"
                            class="group bg-surface-900 rounded-xl p-2.5 flex gap-3 cursor-pointer active:scale-[0.98] transition-all hover:border-primary-500/30 hover:bg-surface-800 relative overflow-hidden shadow-sm"
                            @click="$emit('open-product', product)"
                        >
                            <div class="w-20 h-20 rounded-lg bg-surface-800 shrink-0 overflow-hidden relative border border-surface-700">
                                <img v-if="product.image" :src="product.image" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                                <div v-else class="flex items-center justify-center h-full text-surface-600"><i class="fa-regular fa-image text-xl opacity-50"></i></div>
                                <div v-if="product.old_price" class="absolute top-0 left-0 bg-red-600 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-br shadow-sm z-10">%</div>
                            </div>
                            <div class="flex-1 flex flex-col min-w-0 py-0.5 relative z-10">
                                <div class="font-bold text-white text-sm leading-tight mb-1 line-clamp-2">{{ product.name }}</div>
                                <div class="text-xs text-surface-400 line-clamp-2 leading-snug mb-auto">{{ product.description }}</div>
                                <div class="flex justify-between items-end mt-2">
                                    <div class="flex flex-col">
                                        <span v-if="product.old_price" class="text-[10px] text-surface-500 line-through decoration-red-500/50">R$ {{ formatMoney(product.old_price) }}</span>
                                        <span class="text-primary-400 font-bold text-base leading-none">R$ {{ formatMoney(product.price) }}</span>
                                    </div>
                                    <div class="w-7 h-7 rounded-full bg-surface-800 flex items-center justify-center text-primary-500 group-hover:bg-primary-500 group-hover:text-surface-900 group-hover:border-primary-500 transition-colors"><i class="fa-solid fa-plus text-[10px] font-bold"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="cat.children && cat.children.length > 0" class="flex flex-col gap-6">
                        <div v-for="child in cat.children" :key="child.id">
                            <div class="flex items-center gap-3 mb-3 px-1">
                                <div class="w-1.5 h-1.5 rounded-full bg-surface-600"></div>
                                <h4 class="font-bold text-surface-300 uppercase tracking-wide text-xs">{{ child.name }}</h4>
                                <div class="h-px bg-surface-800 flex-1"></div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                <div v-for="product in child.products" :key="product.id"
                                    class="group bg-surface-900 rounded-xl p-2.5 flex gap-3 cursor-pointer active:scale-[0.98] transition-all hover:border-primary-500/30 hover:bg-surface-800 relative overflow-hidden shadow-sm"
                                    @click="$emit('open-product', product)"
                                >
                                    <div class="w-24 h-24 rounded-lg bg-surface-800 shrink-0 overflow-hidden relative">
                                        <img v-if="product.image" :src="product.image" class="w-full h-full object-cover">
                                        <div v-else class="flex items-center justify-center h-full text-surface-600"><i class="fa-regular fa-image text-xl opacity-50"></i></div>
                                    </div>
                                    <div class="flex-1 flex flex-col min-w-0 py-0.5 relative z-10">
                                        <div class="font-bold text-white text-sm leading-tight mb-1 line-clamp-2">{{ product.name }}</div>
                                        <div class="text-xs text-surface-400 line-clamp-2 leading-snug mb-auto">{{ product.description }}</div>
                                        <div class="flex justify-between items-end mt-2">
                                            <span class="text-primary-400 font-bold text-base leading-none">R$ {{ formatMoney(product.price) }}</span>
                                            <div class="w-7 h-7 rounded-full bg-surface-800 border border-surface-700 flex items-center justify-center text-primary-500 group-hover:bg-primary-500 group-hover:text-surface-900 transition-colors"><i class="fa-solid fa-plus text-[10px] font-bold"></i></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div v-if="visibleCategories.length === 0" class="flex flex-col items-center justify-center py-20 text-surface-500 opacity-60">
                    <i class="fa-solid fa-burger text-5xl mb-4"></i>
                    <p>Nenhum item encontrado.</p>
                </div>
            </template>
        </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useMenuStore } from '@/stores/menu-store';
import { useUserStore } from '@/stores/user-store';

const emit = defineEmits(['open-product']);
const menuStore = useMenuStore();
const userStore = useUserStore();

const searchTerm = ref('');
const activeCat = ref('');
const scrollContainer = ref(null);
const pillsContainer = ref(null);
let observer = null;

const formatMoney = (val) => parseFloat(val).toFixed(2).replace('.', ',');

// --- LÓGICA DE FILTRO E IDADE ---
const getUserAge = () => {
    if (!userStore.isLoggedIn || !userStore.user?.dob) return 0;
    const dob = userStore.user.dob; 
    if (!dob) return 0;
    const birthDate = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    return age;
};

const visibleCategories = computed(() => {
    const term = searchTerm.value.toLowerCase().trim();
    const userAge = getUserAge();
    const isLoggedIn = userStore.isLoggedIn;

    const filterProducts = (list) => {
        return list.filter(p => {
            const matchesText = !term || p.name.toLowerCase().includes(term) || (p.description && p.description.toLowerCase().includes(term));
            
            // Lógica 18+: Só mostra se logado E maior de idade
            if (p.is_18_plus) {
                if (!isLoggedIn || userAge < 18) return false;
            }
            return matchesText;
        });
    };

    return menuStore.categories.map(cat => {
        const catClone = { ...cat };
        catClone.products = filterProducts(catClone.products || []);
        
        if (catClone.children) {
            catClone.children = catClone.children.map(child => {
                const childClone = { ...child };
                childClone.products = filterProducts(childClone.products || []);
                return childClone.products.length > 0 ? childClone : null;
            }).filter(Boolean);
        }

        if (catClone.products.length > 0 || (catClone.children && catClone.children.length > 0)) {
            return catClone;
        }
        return null;
    }).filter(Boolean);
});

// --- SCROLL SPY ---
const initScrollSpy = () => {
    if (observer) observer.disconnect();
    const options = { root: scrollContainer.value, rootMargin: '-15% 0px -60% 0px', threshold: 0 };
    observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                const id = entry.target.id.replace('cat-', '');
                activeCat.value = parseInt(id) || id; 
                const pill = document.getElementById('pill-' + id);
                if (pill && pillsContainer.value) pill.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            }
        });
    }, options);
    nextTick(() => document.querySelectorAll('.category-section').forEach(section => observer.observe(section)));
};

const scrollToCategory = (id) => {
    activeCat.value = id;
    const el = document.getElementById('cat-' + id);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

onMounted(() => {
    // Inicializa ScrollSpy após renderização
    setTimeout(initScrollSpy, 500);
});

onUnmounted(() => {
    if (observer) observer.disconnect();
});
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.animate-fade-in { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>