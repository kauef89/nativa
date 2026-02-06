<template>
  <div class="flex flex-col h-full w-full bg-surface-1 rounded-[24px] overflow-hidden transition-all border-none">
    
    <div class="shrink-0 h-20 px-6 flex items-center justify-between bg-surface-1 border-b border-surface-3/10 z-20">
        <h3 class="text-sm font-black text-surface-0 uppercase tracking-widest flex items-center gap-2">
            <i class="fa-solid fa-bell text-primary-500"></i> Feed
        </h3>
        
        <div class="flex items-center gap-3">
            
            <div class="flex items-center gap-1 bg-surface-2 p-1 rounded-full border border-surface-3/20">
                
                <div 
                    class="w-8 h-8 rounded-full flex items-center justify-center transition-all cursor-help"
                    :class="socketState.bridgeOnline 
                        ? 'bg-green-500/10 text-green-500' 
                        : 'bg-red-500/10 text-red-500 animate-pulse'"
                    v-tooltip.bottom="socketState.bridgeOnline ? 'Bridge Conectado' : 'Bridge OFFLINE (Verifique o iniciar.bat)'"
                >
                    <i class="fa-solid fa-server text-xs"></i>
                </div>

            </div>

            <div class="w-px h-4 bg-surface-3/50 mx-1"></div>

            <Button 
                icon="fa-solid fa-rotate" 
                text rounded size="small" 
                class="!w-8 !h-8 !text-surface-400 hover:!text-surface-0 hover:!bg-surface-2" 
                @click="forceRefresh" 
                :class="{'animate-spin': loading}" 
                v-tooltip.bottom="'Atualizar Feed'"
            />
        </div>
    </div>
    
    <div class="flex-1 overflow-y-auto p-4 space-y-4 scrollbar-thin bg-surface-1 relative">
        
        <div v-if="loading && pendingRequests.length === 0" class="absolute inset-0 flex items-center justify-center bg-surface-1/50 z-10 backdrop-blur-sm">
             <LoadingSpinner size="medium" />
        </div>

        <div v-if="pendingRequests.length > 0" class="flex flex-col gap-3 animate-fade-in">
            <div class="text-[10px] font-bold text-orange-400 uppercase tracking-wider flex items-center gap-2 px-1">
                <span class="relative flex h-2 w-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-orange-500"></span>
                </span>
                Solicitações ({{ pendingRequests.length }})
            </div>
            
            <div v-for="req in pendingRequests" :key="req.id" class="bg-surface-2 border-l-4 border-l-orange-500 border border-transparent rounded-[16px] p-4 shadow-md relative overflow-hidden group hover:bg-surface-3 transition-colors">
                <div class="flex justify-between items-start mb-2">
                    <span class="text-xs font-black text-surface-0 flex items-center gap-2 uppercase tracking-wide">
                        <i :class="getRequestIcon(req.type)"></i>
                        {{ getRequestTitle(req) }}
                    </span>
                    <span class="text-[9px] font-bold text-surface-500">{{ formatDate(req.created_at, true) }}</span>
                </div>
                <p class="text-xs text-surface-300 mb-4 leading-snug font-medium">{{ req.description }}</p>
                <div class="flex justify-between items-center mb-3">
                    <div class="text-[10px] text-surface-400 flex items-center gap-1.5 font-bold">
                        <i class="fa-solid fa-user-tie"></i> 
                        <span class="uppercase tracking-wide">{{ abbreviateName(req.author_name || 'Staff') }}</span>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <button class="bg-surface-1 hover:bg-red-500/10 hover:text-red-400 text-surface-400 border border-surface-3/10 hover:border-red-500/30 rounded-full py-2 text-[10px] font-black transition-all uppercase tracking-wider" @click="promptPin(req, 'reject')">Negar</button>
                    <button class="bg-surface-1 hover:bg-green-500/10 hover:text-green-400 text-surface-400 border border-surface-3/10 hover:border-green-500/30 rounded-full py-2 text-[10px] font-black transition-all uppercase tracking-wider" @click="promptPin(req, 'approve')">Aceitar</button>
                </div>
            </div>
        </div>

        <div v-if="historyLogs.length > 0" class="flex flex-col gap-2">
            <div class="text-[10px] font-black text-surface-500 uppercase tracking-widest px-1 mt-2">Recentes</div>
            
            <div v-for="log in historyLogs" :key="log.id" class="flex gap-3 p-3 rounded-[16px] bg-surface-2 border border-transparent hover:bg-surface-3 transition-colors">
                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 border border-transparent" 
                     :class="getLogIconClass(log)">
                    <i class="fa-solid text-xs" :class="getRequestIcon(log.type)"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between">
                        <span class="text-xs font-bold text-surface-300 truncate">{{ getRequestTitle(log) }}</span>
                        <span class="text-[9px] font-bold text-surface-500">{{ formatDate(log.created_at, true) }}</span>
                    </div>
                    <div class="text-[10px] text-surface-500 truncate mt-0.5 font-medium">{{ log.description }}</div>
                </div>
            </div>
        </div>
        
        <div v-if="pendingRequests.length === 0 && historyLogs.length === 0" class="flex flex-col items-center justify-center py-10 text-surface-600 opacity-60">
            <i class="fa-solid fa-bell-slash text-2xl mb-2"></i>
            <p class="text-[10px] font-bold uppercase">Sem notificações</p>
        </div>

    </div>

    <Dialog v-model:visible="showPinModal" modal header="Confirmação" :style="{ width: '300px' }" 
            :pt="{ root: { class: '!bg-surface-2 !border-none !rounded-[24px]' }, header: { class: '!bg-transparent !text-surface-0 !p-4 !border-none' }, content: { class: '!bg-transparent !p-6' } }">
        <form @submit.prevent="confirmAction" class="flex flex-col gap-4 text-center" autocomplete="off">
            <p class="text-sm text-surface-300">Digite seu PIN para confirmar.</p>
            <InputText v-model="pinInput" type="password" maxlength="4" autocomplete="new-password" name="nativa_activity_pin" data-lpignore="true" 
                class="!text-center !text-2xl !tracking-[0.5em] !font-bold !bg-surface-1 !border-none !text-surface-0 !h-12 !rounded-full focus:!ring-2 focus:!ring-primary-500" 
                placeholder="••••" autoFocus />
            <Button type="submit" label="Confirmar" class="!bg-primary-600 hover:!bg-primary-500 !border-none !w-full !font-black !rounded-full" :loading="processingId !== null" />
        </form>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onActivated, watch } from 'vue';
import { useDeliveryStore } from '@/stores/delivery-store';
import { useFormat } from '@/composables/useFormat'; 
import { socketState } from '@/services/socket'; 
import api from '@/services/api';
import { notify } from '@/services/notify';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import LoadingSpinner from "@/components/shared/LoadingSpinner.vue";

const deliveryStore = useDeliveryStore();
const { formatDate, abbreviateName } = useFormat(); 
const allRequests = ref([]);
const processingId = ref(null);
const loading = ref(false);

const pendingRequests = computed(() => allRequests.value.filter(r => r.status === 'pending'));
const historyLogs = computed(() => allRequests.value.filter(r => r.status !== 'pending').slice(0, 15));

const showPinModal = ref(false);
const pinInput = ref('');
const pendingAction = ref(null); 

// --- MONITORAMENTO BRIDGE ---
watch(
    () => socketState.bridgeOnline,
    (isOnline) => {
        if (!isOnline) {
            // Emite alerta sonoro/visual quando o Bridge cair
            notify('error', '⚠️ Bridge Desconectado', 'O sistema de impressão local parou. Verifique o servidor.');
        }
    }
);

// --- REMOVIDO: Funções de Impressora (getPrinterColor, getPrinterTooltip) ---

const promptPin = (req, action) => {
    pendingAction.value = { req, action };
    pinInput.value = '';
    showPinModal.value = true;
};

const confirmAction = async () => {
    if (!pendingAction.value || !pinInput.value) return;
    const { req, action } = pendingAction.value;
    processingId.value = req.id;
    try {
        const { data } = await api.post('/approve-request', { log_id: req.id, action: action, pin: pinInput.value });
        if (data.success) {
            notify('success', action === 'approve' ? 'Aprovado' : 'Recusado', 'Operação realizada.');
            showPinModal.value = false;
            await fetchAll(); 
        }
    } catch(e) {
        notify('error', 'Erro', e.response?.data?.message || 'Falha ao processar.');
    } finally {
        processingId.value = null;
    }
};

const fetchRequests = async () => {
    try {
        const { data } = await api.get(`/approvals?_t=${Date.now()}`);
        if(data.success) allRequests.value = data.requests;
    } catch(e) { console.error('Erro aprovações', e); }
};

const getRequestTitle = (req) => {
    const map = { 
        'cancel': 'Cancelamento', 
        'swap': 'Troca', 
        'discount': 'Desconto',
        'new_order': 'Novo Pedido',
        'order_cancelled': 'Pedido Cancelado'
    };
    return map[req.type] || 'Solicitação';
};

const getRequestIcon = (type) => {
    if (type === 'cancel' || type === 'order_cancelled') return 'fa-solid fa-trash-can';
    if (type === 'swap') return 'fa-solid fa-rotate';
    if (type === 'new_order') return 'fa-solid fa-receipt';
    return 'fa-solid fa-circle-question';
};

const getLogIconClass = (log) => {
    if (log.type === 'new_order') return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
    if (log.type === 'order_cancelled') return 'bg-red-500/10 text-red-500 border-red-500/20';
    
    if (log.status === 'approved') return 'bg-green-500/10 text-green-500 border-green-500/20';
    if (log.status === 'rejected') return 'bg-red-500/10 text-red-500 border-red-500/20';
    return 'bg-surface-800 text-surface-400 border-surface-700';
};

const fetchAll = async () => {
    loading.value = true;
    try { await Promise.all([fetchRequests(), deliveryStore.fetchOrders()]); } 
    finally { loading.value = false; }
};

const forceRefresh = () => { fetchAll(); };

onMounted(() => { fetchAll(); });
onActivated(() => { fetchAll(); });
</script>