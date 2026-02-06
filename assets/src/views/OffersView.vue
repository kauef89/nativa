<template>
    <div class="flex flex-col h-full w-full overflow-hidden bg-surface-1 rounded-[24px] transition-all">
        
        <div class="shrink-0 h-20 px-6 flex items-center justify-between z-20 bg-surface-1 border-b border-surface-3/10">
            <div>
                <h1 class="text-xl md:text-2xl font-black text-surface-0 flex items-center gap-2">
                    <i class="fa-solid fa-bullhorn text-primary-500"></i>
                    Ofertas <span class="hidden md:inline">de Carrinho</span>
                </h1>
                <p class="text-surface-400 text-xs font-bold hidden md:block">Configure upsells automáticos.</p>
            </div>

            <Button 
                label="Nova Oferta" 
                icon="fa-solid fa-plus" 
                class="hidden md:flex !bg-primary-500 hover:!bg-primary-400 !text-surface-950 !font-black !border-none !rounded-full shadow-lg"
                @click="openCreateModal"
            />

            <Button 
                icon="fa-solid fa-plus" 
                rounded
                class="md:hidden !w-10 !h-10 !bg-primary-500 !text-surface-950 !border-none !shadow-lg shrink-0"
                @click="openCreateModal" 
            />
        </div>

        <div class="flex-1 overflow-y-auto p-4 md:p-6 scrollbar-thin">
            
            <div v-if="loading" class="h-full flex items-center justify-center">
                <i class="fa-solid fa-circle-notch fa-spin text-3xl text-primary-500"></i>
            </div>

            <div v-if="offers.length === 0 && !loading" class="h-full flex flex-col items-center justify-center text-surface-500 opacity-60">
                <i class="fa-solid fa-tags text-6xl mb-4 text-surface-600"></i>
                <p class="text-lg font-black">Nenhuma oferta ativa</p>
                <p class="text-sm font-bold text-center">Crie sua primeira oferta de upsell.</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 pb-20 md:pb-0">
                <div 
                    v-for="offer in offers" 
                    :key="offer.id"
                    class="bg-surface-2 rounded-[24px] p-5 hover:bg-surface-3 transition-colors group relative overflow-hidden flex flex-col shadow-lg border border-surface-3/10"
                >
                    <div class="flex justify-between items-start mb-3">
                        <div 
                            class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border"
                            :class="offer.acf?.oferta_status ? 'bg-green-500/10 text-green-400 border-green-500/20' : 'bg-red-500/10 text-red-400 border-red-500/20'"
                        >
                            {{ offer.acf?.oferta_status ? 'Ativa' : 'Inativa' }}
                        </div>
                        <div class="flex gap-2">
                            <button 
                                class="w-8 h-8 rounded-full bg-surface-1 hover:bg-surface-3 text-surface-400 hover:text-surface-0 flex items-center justify-center transition-colors shadow-sm"
                                @click="editOffer(offer)"
                            >
                                <i class="fa-solid fa-pen text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <h3 class="text-lg font-black text-surface-0 mb-1 leading-tight line-clamp-2 min-h-[1.75rem]">
                        {{ getOfferTitle(offer) }}
                    </h3>
                    
                    <div class="mb-4 flex-1">
                        <div v-if="hasRules(offer)" class="flex flex-wrap gap-1 mt-2">
                            <template v-if="offer.acf?.grupos_de_regras">
                                <span class="text-[9px] bg-surface-1 text-surface-400 px-2 py-1 rounded-full font-bold border border-surface-3/10">
                                    Lógica Avançada ({{ offer.acf.grupos_de_regras.length }} grupos)
                                </span>
                            </template>
                            <template v-else>
                                <span v-for="(rule, idx) in offer.acf.regras_de_ativacao" :key="idx" class="text-[9px] bg-surface-1 text-surface-400 px-2 py-1 rounded-full font-bold border border-surface-3/10">
                                    {{ formatRuleSummary(rule) }}
                                </span>
                            </template>
                        </div>
                        <span v-else class="text-[10px] text-surface-600 italic font-bold">Sempre ativa (sem regras)</span>
                    </div>

                    <div class="flex items-center gap-3 bg-surface-1 p-3 rounded-[16px] border border-surface-3/10 mt-auto">
                        <div class="w-10 h-10 rounded-full bg-surface-2 flex items-center justify-center text-surface-500 overflow-hidden shrink-0">
                            <i class="fa-solid fa-box-open"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="text-[9px] text-surface-500 block uppercase font-black tracking-wider">Por Apenas</span>
                            <div class="text-primary-400 font-black text-lg leading-none">
                                {{ formatCurrency(offer.acf?.preco_promocional) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <OfferEditorDrawer v-model="showDrawer" :offerToEdit="selectedOffer" @saved="handleSaved" />
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useFormat } from '@/composables/useFormat'; 
import Button from 'primevue/button';
import OfferEditorDrawer from '@/components/manager/OfferEditorDrawer.vue';

const { formatCurrency } = useFormat(); 
const offers = ref([]);
const loading = ref(false);
const showDrawer = ref(false);
const selectedOffer = ref(null);

const fetchOffers = async () => {
    loading.value = true;
    try {
        const rootUrl = window.nativaData?.root || '/wp-json/';
        const nonce = window.nativaData?.nonce || '';
        const { data } = await axios.get(`${rootUrl}wp/v2/nativa_oferta?status=publish,draft&acf_format=standard&per_page=100`, { headers: { 'X-WP-Nonce': nonce } });
        offers.value = Array.isArray(data) ? data : [];
    } catch (e) { offers.value = []; } finally { loading.value = false; }
};

const getOfferTitle = (offer) => (offer.acf?.texto_da_oferta || offer.title?.rendered || 'Sem título');
const hasRules = (offer) => (offer.acf?.regras_de_ativacao?.length > 0 || offer.acf?.grupos_de_regras?.length > 0);
const formatRuleSummary = (rule) => { if (rule.tipo_regra === 'subtotal_carrinho') return `Subtotal >= ${formatCurrency(rule.valor)}`; if (rule.tipo_regra === 'categoria_no_carrinho') return `Contém Categoria`; if (rule.tipo_regra === 'produto_no_carrinho') return `Contém Produto`; return 'Regra'; };

const openCreateModal = () => { selectedOffer.value = null; showDrawer.value = true; };
const editOffer = (offer) => { selectedOffer.value = offer; showDrawer.value = true; };
const handleSaved = () => { fetchOffers(); };

onMounted(fetchOffers);
</script>