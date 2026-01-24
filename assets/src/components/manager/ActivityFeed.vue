<template>
  <div class="flex flex-col h-full bg-surface-900 border-l border-surface-800 shrink-0">
    <div class="p-4 border-b border-surface-800 bg-surface-950 flex justify-between items-center">
        <h3 class="text-sm font-bold text-surface-400 uppercase tracking-widest">Feed & Solicitações</h3>
        <Button 
            icon="fa-solid fa-rotate" 
            text rounded size="small" 
            class="!w-6 !h-6 !text-surface-500 hover:!text-white" 
            @click="forceRefresh" 
            :class="{'animate-spin': loading}" 
            v-tooltip.bottom="'Atualizar agora'"
        />
    </div>
    
    <div class="flex-1 overflow-y-auto p-4 space-y-6 scrollbar-thin">
        
        <div v-if="pendingRequests.length > 0" class="flex flex-col gap-3 animate-fade-in">
            <div class="text-xs font-bold text-orange-400 uppercase tracking-wider flex items-center gap-2 px-1">
                <span class="relative flex h-2 w-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-orange-500"></span>
                </span>
                Pendentes ({{ pendingRequests.length }})
            </div>
            
            <div v-for="req in pendingRequests" :key="req.id" class="bg-surface-800 border-l-4 border-l-orange-500 border-y border-r border-surface-700 rounded-r-xl p-3 shadow-lg relative overflow-hidden group hover:bg-surface-750 transition-colors">
                
                <div class="flex justify-between items-start mb-1">
                    <span class="text-xs font-bold text-white flex items-center gap-2">
                        <i :class="getRequestIcon(req.type)"></i>
                        {{ getRequestTitle(req) }}
                    </span>
                    <span class="text-[9px] text-surface-500 font-mono">{{ formatTime(req.created_at) }}</span>
                </div>
                
                <p class="text-xs text-surface-300 mb-2 leading-snug bg-surface-900/50 p-2 rounded border border-surface-700/50 font-medium">
                    {{ req.description }}
                </p>
                
                <div class="flex justify-between items-center mb-3">
                    <div class="text-[10px] text-surface-400 flex items-center gap-1.5 bg-surface-900/30 px-2 py-0.5 rounded-full">
                        <i class="fa-solid fa-user-tie text-surface-500"></i> 
                        <span class="uppercase tracking-wide">{{ req.author_name || 'Staff' }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button 
                        class="bg-red-500/10 hover:bg-red-600 hover:text-white text-red-400 border border-red-500/30 rounded-lg py-1.5 text-xs font-bold transition-all flex items-center justify-center gap-1 active:scale-95" 
                        @click="promptPin(req, 'reject')"
                    >
                        <i class="fa-solid fa-xmark"></i> Negar
                    </button>
                    <button 
                        class="bg-green-500/10 hover:bg-green-600 hover:text-white text-green-400 border border-green-500/30 rounded-lg py-1.5 text-xs font-bold transition-all flex items-center justify-center gap-1 active:scale-95" 
                        @click="promptPin(req, 'approve')"
                    >
                        <i class="fa-solid fa-check"></i> Aceitar
                    </button>
                </div>
            </div>
        </div>

        <div v-if="historyLogs.length > 0" class="flex flex-col gap-2">
            <div class="text-[10px] font-bold text-surface-500 uppercase tracking-widest px-1">Histórico Recente</div>
            
            <div v-for="log in historyLogs" :key="log.id" class="flex gap-3 p-3 rounded-lg bg-surface-950/50 border border-surface-800 opacity-80 hover:opacity-100 transition-opacity">
                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" 
                     :class="log.status === 'approved' ? 'bg-green-500/20 text-green-500' : 'bg-red-500/20 text-red-500'">
                    <i class="fa-solid" :class="log.status === 'approved' ? 'fa-check' : 'fa-xmark'"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between">
                        <span class="text-xs font-bold text-surface-300 truncate">{{ getRequestTitle(log) }}</span>
                        <span class="text-[9px] text-surface-500 font-mono">{{ formatTime(log.created_at) }}</span>
                    </div>
                    <div class="text-[10px] text-surface-500 truncate">{{ log.description }}</div>
                    <div class="text-[9px] text-surface-600 mt-1 flex items-center gap-1">
                        <i class="fa-solid fa-user-check"></i> 
                        {{ log.status === 'approved' ? 'Aprovado' : 'Negado' }} por {{ log.approver_name }}
                    </div>
                </div>
            </div>
        </div>
        
        <div v-if="pendingRequests.length === 0 && historyLogs.length === 0" class="flex flex-col items-center justify-center py-10 text-surface-600 opacity-60">
            <i class="fa-solid fa-bell-slash text-3xl mb-2"></i>
            <p class="text-xs">Nenhuma atividade recente.</p>
        </div>

    </div>

    <Dialog v-model:visible="showPinModal" modal header="Confirmação de Segurança" :style="{ width: '300px' }" 
            :pt="{ root: { class: '!bg-surface-900 !border !border-surface-700 !rounded-xl' }, header: { class: '!bg-surface-950 !text-white !p-4' }, content: { class: '!bg-surface-900 !p-6' } }">
        <form @submit.prevent="confirmAction" class="flex flex-col gap-4 text-center">
            <p class="text-sm text-surface-300">Digite seu PIN para confirmar.</p>
            
            <input type="text" autocomplete="username" class="hidden" /> <InputText 
                v-model="pinInput" 
                type="password" 
                maxlength="4" 
                autocomplete="current-password"
                class="!text-center !text-2xl !tracking-[0.5em] !font-mono !bg-surface-950 !border-surface-700 !text-white !h-12 !rounded-lg focus:!border-primary-500" 
                placeholder="••••" 
                autoFocus
            />
            
            <Button type="submit" label="Confirmar" class="!bg-primary-600 hover:!bg-primary-500 !border-none !w-full !font-bold" :loading="processingId !== null" />
        </form>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useDeliveryStore } from '@/stores/delivery-store';
import api from '@/services/api';
import { notify } from '@/services/notify';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';

const deliveryStore = useDeliveryStore();
const allRequests = ref([]);
const processingId = ref(null);
const loading = ref(false);

const pendingRequests = computed(() => allRequests.value.filter(r => r.status === 'pending'));
const historyLogs = computed(() => allRequests.value.filter(r => r.status !== 'pending'));

const showPinModal = ref(false);
const pinInput = ref('');
const pendingAction = ref(null); 

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
        const { data } = await api.post('/approve-request', {
            log_id: req.id,
            action: action,
            pin: pinInput.value
        });
        
        if (data.success) {
            notify('success', action === 'approve' ? 'Solicitação Aprovada' : 'Solicitação Recusada', 'Operação realizada com sucesso.');
            showPinModal.value = false;
            // Atualiza a lista completa (o backend já deve retornar o item com status novo na próxima chamada)
            await fetchAll(); 
        }
    } catch(e) {
        notify('error', 'Erro', e.response?.data?.message || 'Falha ao processar (PIN Incorreto?).');
    } finally {
        processingId.value = null;
    }
};

const fetchRequests = async () => {
    try {
        const { data } = await api.get('/approvals');
        if(data.success) {
            allRequests.value = data.requests;
        }
    } catch(e) { 
        console.error('Erro ao buscar aprovações', e); 
    }
};

const getRequestTitle = (req) => {
    const map = { 'cancel': 'Cancelamento', 'swap': 'Troca de Item', 'discount': 'Desconto' };
    return map[req.type] || 'Solicitação';
};

const getRequestIcon = (type) => {
    if (type === 'cancel') return 'fa-solid fa-trash-can text-red-400';
    if (type === 'swap') return 'fa-solid fa-rotate text-blue-400';
    return 'fa-solid fa-circle-question text-orange-400';
};

const formatTime = (iso) => iso ? new Date(iso).toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'}) : '';

const fetchAll = async () => {
    loading.value = true;
    try {
        await Promise.all([fetchRequests(), deliveryStore.fetchOrders()]);
    } finally {
        loading.value = false;
    }
};

const forceRefresh = () => {
    notify('info', 'Atualizando...', 'Buscando novos dados.');
    fetchAll();
};

const setupPushListener = () => {
    if (window.OneSignal) {
        window.OneSignal.push(() => {
            window.OneSignal.on('notificationDisplay', (event) => {
                const type = event.notification.additionalData?.type;
                if (['new_request', 'request_processed'].includes(type)) {
                    fetchAll(); 
                }
            });
        });
    }
};

onMounted(() => {
    fetchAll();
    setupPushListener();
});
</script>