<template>
  <div class="flex flex-col h-full w-full bg-transparent text-surface-200 overflow-hidden relative">
    
    <div class="flex-1 overflow-y-auto scrollbar-hide pb-32" ref="scrollContainer">
        
        <ClientHome v-if="activeTab === 'home'" @go-to-menu="handleTabChange('menu')" />

        <div v-show="activeTab === 'menu'" class="flex flex-col min-h-full bg-surface-950/95 backdrop-blur-sm rounded-t-3xl mt-2 shadow-2xl border-t border-surface-800 transition-all duration-500">
            
            <div class="sticky top-0 bg-surface-950/95 backdrop-blur z-20 px-4 py-4 border-b border-surface-800 shadow-sm rounded-t-3xl">
                <div class="flex gap-2 overflow-x-auto scrollbar-hide">
                    <button 
                        class="px-4 py-2 rounded-full text-sm font-bold whitespace-nowrap transition-all border"
                        :class="activeCat === 'all' 
                            ? 'bg-primary-500 text-surface-900 border-primary-500 shadow-lg shadow-primary-500/20' 
                            : 'bg-surface-800 text-surface-400 border-surface-700 hover:bg-surface-700'"
                        @click="activeCat = 'all'"
                    >
                        Todos
                    </button>
                    <button 
                        v-for="cat in menuStore.categories" 
                        :key="cat.id"
                        class="px-4 py-2 rounded-full text-sm font-bold whitespace-nowrap transition-all border"
                        :class="activeCat === cat.id 
                            ? 'bg-primary-500 text-surface-900 border-primary-500 shadow-lg shadow-primary-500/20' 
                            : 'bg-surface-800 text-surface-400 border-surface-700 hover:bg-surface-700'"
                        @click="activeCat = cat.id"
                    >
                        {{ cat.name }}
                    </button>
                </div>
            </div>

            <div class="p-4 space-y-8 animate-fade-in">
                <div v-if="menuStore.isLoading" class="flex flex-col items-center justify-center p-20 text-primary-500">
                    <i class="fa-solid fa-spinner fa-spin text-4xl mb-4"></i>
                    <p class="text-sm font-bold uppercase tracking-wider animate-pulse">Carregando cardápio...</p>
                </div>

                <div v-else v-for="cat in filteredCategories" :key="cat.id">
                    <div class="flex items-center gap-3 mb-4">
                        <h3 class="font-black text-white text-xl tracking-tight">{{ cat.name }}</h3>
                        <div class="h-px bg-surface-800 flex-1"></div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="product in cat.products" :key="product.id"
                             class="group bg-surface-900 border border-surface-800 rounded-2xl p-3 flex gap-4 cursor-pointer active:scale-[0.98] transition-all hover:border-primary-500/30 hover:bg-surface-800 relative overflow-hidden"
                             @click="openProduct(product)"
                        >
                            <div class="w-28 h-28 rounded-xl bg-surface-800 shrink-0 overflow-hidden relative border border-surface-700 shadow-inner">
                                <img v-if="product.image" :src="product.image" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                                <div v-else class="flex items-center justify-center h-full text-surface-600">
                                    <i class="fa-regular fa-image text-3xl opacity-50"></i>
                                </div>
                                <div v-if="product.old_price" class="absolute top-0 left-0 bg-red-600 text-white text-[10px] font-bold px-2 py-1 rounded-br-lg shadow-sm z-10">
                                    %
                                </div>
                            </div>

                            <div class="flex-1 flex flex-col min-w-0 py-1 relative z-10">
                                <div class="font-bold text-white text-base leading-tight mb-1 line-clamp-2">{{ product.name }}</div>
                                <div class="text-xs text-surface-400 line-clamp-2 leading-relaxed mb-auto">{{ product.description }}</div>
                                
                                <div class="flex justify-between items-end mt-3">
                                    <div class="flex flex-col">
                                        <span v-if="product.old_price" class="text-[10px] text-surface-500 line-through decoration-red-500/50">R$ {{ formatMoney(product.old_price) }}</span>
                                        <span class="text-primary-400 font-bold font-mono text-lg leading-none">R$ {{ formatMoney(product.price) }}</span>
                                    </div>
                                    <div class="w-8 h-8 rounded-full bg-surface-800 border border-surface-700 flex items-center justify-center text-primary-500 group-hover:bg-primary-500 group-hover:text-surface-900 group-hover:border-primary-500 transition-colors shadow-sm">
                                        <i class="fa-solid fa-plus text-xs"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!menuStore.isLoading && filteredCategories.length === 0" class="flex flex-col items-center justify-center py-20 text-surface-500 opacity-60">
                    <i class="fa-solid fa-burger text-5xl mb-4"></i>
                    <p>Nenhum produto encontrado nesta categoria.</p>
                </div>
            </div>
        </div>

        <div v-if="activeTab === 'profile'" class="flex flex-col min-h-full animate-fade-in bg-transparent rounded-t-3xl mt-4 shadow-2xl border-t border-surface-800">
            
            <div v-if="!userStore.isLoggedIn" class="flex flex-col items-center justify-center flex-1 p-6 min-h-[70vh]">
                
                <div class="relative w-full max-w-sm bg-surface-900/40 backdrop-blur-2xl border border-white/10 rounded-[2.5rem] p-8 shadow-[0_20px_50px_rgba(0,0,0,0.5)] overflow-hidden group">
                    
                    <div class="absolute -top-20 -right-20 w-48 h-48 bg-primary-500/20 rounded-full blur-[60px] group-hover:bg-primary-500/30 transition-colors duration-1000"></div>
                    <div class="absolute -bottom-20 -left-20 w-48 h-48 bg-blue-500/10 rounded-full blur-[60px] group-hover:bg-blue-500/20 transition-colors duration-1000"></div>

                    <div class="relative z-10 flex flex-col items-center text-center">
                        
                        <div class="w-24 h-24 bg-gradient-to-b from-surface-800 to-surface-950 rounded-3xl flex items-center justify-center mb-6 shadow-inner border border-white/5 relative">
                            <div class="absolute inset-0 rounded-3xl border border-white/10 mask-gradient"></div>
                            <i class="fa-solid fa-user-astronaut text-4xl text-primary-400 drop-shadow-[0_0_15px_rgba(52,211,153,0.5)]"></i>
                        </div>

                        <h2 class="text-3xl font-black text-white mb-2 tracking-tight">Bem-vindo!</h2>
                        <p class="text-surface-400 text-sm mb-10 leading-relaxed font-medium">
                            Acesse sua conta para acompanhar pedidos, acumular pontos e gerenciar endereços.
                        </p>

                        <div class="w-full bg-white/5 p-1.5 rounded-2xl border border-white/10 backdrop-blur-md shadow-lg hover:bg-white/10 transition-colors">
                            <div id="google-btn-wrapper" class="w-full flex justify-center min-h-[50px]"></div>
                        </div>

                        <div class="mt-8 flex items-center gap-2 opacity-60">
                            <i class="fa-solid fa-shield-halved text-primary-500 text-xs"></i>
                            <span class="text-[10px] text-surface-400 uppercase tracking-widest font-bold">Ambiente 100% Seguro</span>
                        </div>
                    </div>
                </div>

            </div>

            <div v-else class="flex flex-col h-full">
                <div class="p-8 pb-10 flex flex-col items-center bg-surface-900 border-b border-surface-800 rounded-b-[2.5rem] shadow-2xl relative z-10">
                    <img :src="userStore.user.avatar || 'https://www.gravatar.com/avatar/?d=mp'" class="w-24 h-24 rounded-full border-4 border-surface-800 shadow-xl mb-4" alt="Avatar">
                    <h2 class="text-2xl font-black text-white">{{ userStore.user.name }}</h2>
                    <p class="text-surface-400 text-sm">{{ userStore.user.email }}</p>
                </div>

                <div class="flex-1 p-6 space-y-3">
                    <h3 class="text-xs font-bold text-surface-500 uppercase tracking-widest mb-2 ml-2">Minha Conta</h3>
                    
                    <button class="w-full bg-surface-900/50 border border-surface-800 rounded-xl p-4 flex items-center gap-4 hover:bg-surface-800 transition-colors group">
                        <div class="w-10 h-10 rounded-full bg-primary-500/10 flex items-center justify-center text-primary-500 group-hover:bg-primary-500 group-hover:text-white transition-colors">
                            <i class="fa-solid fa-receipt"></i>
                        </div>
                        <div class="flex-1 text-left">
                            <div class="text-white font-bold">Meus Pedidos</div>
                            <div class="text-surface-500 text-xs">Histórico e status</div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-surface-600"></i>
                    </button>

                    <button class="w-full bg-surface-900/50 border border-surface-800 rounded-xl p-4 flex items-center gap-4 hover:bg-surface-800 transition-colors group">
                        <div class="w-10 h-10 rounded-full bg-blue-500/10 flex items-center justify-center text-blue-500 group-hover:bg-blue-500 group-hover:text-white transition-colors">
                            <i class="fa-solid fa-map-location-dot"></i>
                        </div>
                        <div class="flex-1 text-left">
                            <div class="text-white font-bold">Meus Endereços</div>
                            <div class="text-surface-500 text-xs">Gerenciar locais</div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-surface-600"></i>
                    </button>

                    <div class="pt-6">
                        <button @click="userStore.logout" class="w-full border border-red-500/30 text-red-400 font-bold py-3 rounded-xl hover:bg-red-500/10 transition-colors flex items-center justify-center gap-2">
                            <i class="fa-solid fa-right-from-bracket"></i> Sair da conta
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <ClientBottomNav 
        :activeTab="activeTab" 
        :cartCount="cartStore.totalItems"
        @update:activeTab="handleTabChange"
        @trigger="handleNavTrigger"
    />

    <ContactSheet ref="contactSheet" />
    <CartDrawer displayMode="bottom" />
    <ProductDetailsModal ref="detailsModal" displayMode="bottom" />
    <ComboWizardModal ref="comboWizard" displayMode="bottom" />

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useMenuStore } from '@/stores/menu-store';
import { useCartStore } from '@/stores/cart-store';
import { useUserStore } from '@/stores/user-store';

// Componentes
import ClientBottomNav from '@/components/ClientBottomNav.vue';
import ClientHome from '@/components/ClientHome.vue';
import ContactSheet from '@/components/ContactSheet.vue';
import CartDrawer from '@/components/CartDrawer.vue';
import ProductDetailsModal from '@/components/ProductDetailsModal.vue';
import ComboWizardModal from '@/components/ComboWizardModal.vue';

const route = useRoute();
const router = useRouter();
const menuStore = useMenuStore();
const cartStore = useCartStore();
const userStore = useUserStore();

// Estado da Navegação
const activeTab = ref('home'); 
const activeCat = ref('all');
const scrollContainer = ref(null);

// Referências aos Modais
const contactSheet = ref(null);
const detailsModal = ref(null);
const comboWizard = ref(null);

const formatMoney = (val) => parseFloat(val).toFixed(2).replace('.', ',');

// Filtro de Categorias
const filteredCategories = computed(() => {
    if (activeCat.value === 'all') return menuStore.categories;
    const parent = menuStore.categories.find(c => c.id === activeCat.value);
    if (parent) {
        let list = [parent];
        if (parent.children && parent.children.length > 0) {
            list = list.concat(parent.children);
        }
        return list;
    }
    return menuStore.categories.filter(c => c.id === activeCat.value);
});

// --- LÓGICA DE LOGIN GOOGLE ---

// Renderiza o script do Google apenas quando necessário
const renderGoogleButton = () => {
    if (document.getElementById('google-client-script')) {
        initGoogleBtn(); // Script já existe, só reinicia o botão
        return;
    }
    const script = document.createElement('script');
    script.id = 'google-client-script';
    script.src = "https://accounts.google.com/gsi/client";
    script.async = true;
    script.onload = initGoogleBtn;
    document.body.appendChild(script);
};

// Função de inicialização do botão Google
const initGoogleBtn = () => {
    /* global google */
    if (typeof google === 'undefined') return;

    const clientId = '855300367759-q1apf6ltpunkmfie5nikp8g20fuvdjso.apps.googleusercontent.com';

    google.accounts.id.initialize({
        client_id: clientId,
        callback: handleGoogleCallback,
        auto_select: false,
        cancel_on_tap_outside: false
    });

    const container = document.getElementById("google-btn-wrapper");
    if (container) {
        google.accounts.id.renderButton(
            container,
            { 
                theme: "outline", 
                size: "large", 
                width: "350", // <--- CORREÇÃO: Valor fixo em pixels (string), não porcentagem
                text: "continue_with", 
                shape: "pill" 
            }
        );
    }
};

const handleGoogleCallback = async (response) => {
    const success = await userStore.loginWithGoogle(response.credential);
    if (success) {
        console.log("Login realizado com sucesso!");
    }
};

// Monitora mudança de aba para carregar o botão Google se necessário
watch(activeTab, (newTab) => {
    if (newTab === 'profile' && !userStore.isLoggedIn) {
        // Pequeno delay para garantir que o DOM renderizou o container
        setTimeout(renderGoogleButton, 100);
    }
});

// --- NAVEGAÇÃO ---

const handleTabChange = (newTab) => {
    if (activeTab.value === newTab && scrollContainer.value) {
        scrollContainer.value.scrollTo({ top: 0, behavior: 'smooth' });
    }
    activeTab.value = newTab;
    
    if (newTab === 'home') router.replace('/home');
    else if (newTab === 'menu') router.replace('/cardapio');
};

const handleNavTrigger = (action) => {
    if (action === 'cart') cartStore.isOpen = true;
    else if (action === 'contact') contactSheet.value.open();
};

const setTabFromRoute = () => {
    if (route.path.includes('/cardapio')) activeTab.value = 'menu';
    else if (route.path.includes('/home')) activeTab.value = 'home';
};

// --- AÇÕES CARDÁPIO ---

const openProduct = (product) => {
    if (product.type === 'combo') comboWizard.value.open(product);
    else if (product.modifiers && product.modifiers.length > 0) {
        detailsModal.value.open(product, (item) => cartStore.addItem(item));
    } else {
        cartStore.addItem({ ...product, qty: 1, price: parseFloat(product.price), modifiers: [] });
    }
};

onMounted(() => {
    menuStore.fetchMenu();
    userStore.initializeSession(); // <--- CORREÇÃO: Nome atualizado
    setTabFromRoute();
});

watch(() => route.path, setTabFromRoute);
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.animate-fade-in { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>