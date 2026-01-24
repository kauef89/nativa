<template>
  <div class="flex flex-col h-full w-full max-w-[100vw] bg-surface-950 overflow-hidden">
    
    <div class="bg-surface-950 border-b border-surface-800 shadow-sm shrink-0 z-30 relative transition-all">
        
        <div v-if="!hideLocalSearch" class="px-4 pt-3 pb-2">
            <div class="relative group">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-surface-500 group-focus-within:text-primary-500 transition-colors"></i>
                <input 
                    v-model="localSearchTerm"
                    type="text" 
                    placeholder="Buscar no cardápio..." 
                    class="w-full bg-surface-900 border border-surface-800 text-white pl-11 pr-4 h-11 rounded-xl focus:outline-none focus:border-primary-500 transition-all shadow-inner text-sm placeholder:text-surface-600 appearance-none"
                />
            </div>
        </div>

        <div class="flex gap-2 overflow-x-auto scrollbar-hide items-center px-4 w-full pb-3" 
             :class="{'pt-3': hideLocalSearch}"
             ref="pillsContainer">
            <button 
                v-for="cat in visibleCategories" 
                :key="cat.id"
                :id="'pill-' + cat.id"
                class="px-4 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition-all border snap-center active:scale-95"
                :class="activeCat === cat.id 
                    ? 'bg-primary-500 text-surface-900 border-primary-500 shadow-lg shadow-primary-500/20' 
                    : 'bg-surface-900 text-surface-400 border-surface-800 hover:bg-surface-800 hover:text-white'"
                @click="scrollToCategory(cat.id)"
            >
                {{ cat.name }}
            </button>
            <div class="w-2 shrink-0"></div>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto scrollbar-hide pb-32 scroll-smooth bg-surface-950 w-full" ref="scrollContainer">
        
        <div class="p-4 space-y-8 animate-fade-in min-h-full">
            
            <div v-if="menuStore.isLoading" class="flex flex-col items-center justify-center pt-20 text-primary-500">
                <i class="fa-solid fa-spinner fa-spin text-3xl mb-4"></i>
                <p class="text-xs font-bold uppercase tracking-wider animate-pulse">Carregando...</p>
            </div>

            <template v-else>
                <div v-for="cat in visibleCategories" :key="cat.id" :id="'cat-' + cat.id" class="category-section scroll-mt-36">
                    
                    <div class="flex flex-col items-start gap-1 mb-3 pt-2">
                        <h3 class="font-black text-white text-lg tracking-tight uppercase flex items-center gap-2">
                            <span class="w-1.5 h-6 bg-primary-500 rounded-full"></span>
                            {{ cat.name }}
                        </h3>
                    </div>

                    <div v-if="cat.products && cat.products.length > 0" class="grid grid-cols-1 gap-3">
                        <div v-for="product in cat.products" :key="product.id"
                            class="group bg-surface-900 border border-surface-800/50 rounded-xl p-2.5 flex gap-3 cursor-pointer active:bg-surface-800 transition-colors relative overflow-hidden shadow-sm"
                            @click="$emit('open-product', product)"
                        >
                            <div class="w-20 h-20 rounded-lg bg-surface-800 shrink-0 overflow-hidden relative border border-surface-700/50">
                                <img v-if="product.image" :src="product.image" class="w-full h-full object-cover">
                                <div v-else class="flex items-center justify-center h-full text-surface-700"><i class="fa-regular fa-image text-xl"></i></div>
                                <div v-if="product.old_price" class="absolute top-0 left-0 bg-red-600 text-white text-[8px] font-black px-1.5 py-0.5 rounded-br z-10">OFERTA</div>
                            </div>

                            <div class="flex-1 flex flex-col min-w-0 py-0.5 justify-between">
                                <div>
                                    <div class="font-bold text-surface-100 text-sm leading-tight mb-1 line-clamp-2">{{ product.name }}</div>
                                    <div class="text-[11px] text-surface-500 line-clamp-2 leading-snug">{{ product.description }}</div>
                                </div>
                                <div class="flex justify-between items-end mt-1">
                                    <div class="flex flex-col">
                                        <span v-if="product.old_price" class="text-[10px] text-surface-500 line-through">R$ {{ formatMoney(product.old_price) }}</span>
                                        <span class="text-primary-400 font-bold text-sm">R$ {{ formatMoney(product.price) }}</span>
                                    </div>
                                    <div class="w-6 h-6 rounded-full bg-surface-800 border border-surface-700 flex items-center justify-center text-primary-500">
                                        <i class="fa-solid fa-plus text-[10px]"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="cat.children && cat.children.length > 0" class="flex flex-col gap-4 mt-4">
                        <div v-for="child in cat.children" :key="child.id">
                            <h4 class="text-xs font-bold text-surface-500 uppercase tracking-widest mb-2 pl-1">{{ child.name }}</h4>
                            <div class="grid grid-cols-1 gap-3">
                                <div v-for="product in child.products" :key="product.id"
                                    class="group bg-surface-900 border border-surface-800/50 rounded-xl p-2.5 flex gap-3 cursor-pointer active:bg-surface-800 transition-colors shadow-sm"
                                    @click="$emit('open-product', product)"
                                >
                                    <div class="w-20 h-20 rounded-lg bg-surface-800 shrink-0 overflow-hidden relative border border-surface-700/50">
                                        <img v-if="product.image" :src="product.image" class="w-full h-full object-cover">
                                        <div v-else class="flex items-center justify-center h-full text-surface-700"><i class="fa-regular fa-image text-xl"></i></div>
                                    </div>
                                    <div class="flex-1 flex flex-col min-w-0 py-0.5 justify-between">
                                        <div>
                                            <div class="font-bold text-surface-100 text-sm leading-tight mb-1 line-clamp-2">{{ product.name }}</div>
                                            <div class="text-[11px] text-surface-500 line-clamp-2 leading-snug">{{ product.description }}</div>
                                        </div>
                                        <div class="flex justify-between items-end mt-1">
                                            <span class="text-primary-400 font-bold text-sm">R$ {{ formatMoney(product.price) }}</span>
                                            <div class="w-6 h-6 rounded-full bg-surface-800 border border-surface-700 flex items-center justify-center text-primary-500">
                                                <i class="fa-solid fa-plus text-[10px]"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div v-if="visibleCategories.length === 0" class="flex flex-col items-center justify-center py-20 text-surface-600 opacity-60">
                    <i class="fa-solid fa-burger text-4xl mb-3"></i>
                    <p class="text-sm">Nenhum item encontrado.</p>
                </div>
            </template>
        </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useMenuStore } from '@/stores/menu-store';
import { useUserStore } from '@/stores/user-store';

const props = defineProps({
    externalSearch: { type: String, default: '' },
    hideLocalSearch: { type: Boolean, default: false }
});

const emit = defineEmits(['open-product']);
const menuStore = useMenuStore();
const userStore = useUserStore();

const localSearchTerm = ref('');
const activeCat = ref('');
const scrollContainer = ref(null);
const pillsContainer = ref(null);
let observer = null;

const formatMoney = (val) => parseFloat(val).toFixed(2).replace('.', ',');

// Sincroniza busca externa (do WaiterView) com a interna
watch(() => props.externalSearch, (val) => {
    localSearchTerm.value = val;
});

const getUserAge = () => {
    if (!userStore.isLoggedIn || !userStore.user?.dob) return 0;
    const dob = userStore.user.dob; 
    if (!dob) return 0;
    const birthDate = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
    return age;
};

const isStaff = computed(() => userStore.isStaff);

const visibleCategories = computed(() => {
    const term = localSearchTerm.value.toLowerCase().trim();
    const userAge = getUserAge();
    const isLoggedIn = userStore.isLoggedIn;

    const filterProducts = (list) => {
        return list.filter(p => {
            const matchesText = !term || p.name.toLowerCase().includes(term) || (p.description && p.description.toLowerCase().includes(term));
            if (p.is_18_plus && !isStaff.value) {
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

onMounted(async () => {
    if (menuStore.categories.length === 0) {
        await menuStore.fetchMenu();
    }
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