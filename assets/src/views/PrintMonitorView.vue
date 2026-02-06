<template>
  <div class="flex flex-col h-full w-full bg-surface-1 rounded-[24px] transition-all overflow-hidden">
    
    <div class="shrink-0 h-20 px-6 flex items-center justify-between z-20 border-b border-surface-3/10">
        <div>
            <h1 class="text-2xl font-black text-surface-0 flex items-center gap-3">
                <i class="fa-solid fa-print text-primary-500"></i> Monitor de Impressão
            </h1>
            <p class="text-surface-400 text-xs font-bold">Histórico e Diagnóstico de Hardware</p>
        </div>
        
        <div class="flex items-center gap-3">
            
            <div class="hidden md:flex items-center gap-1 bg-surface-2 p-1 rounded-full border border-surface-3/20">
                
                <div 
                    class="flex items-center gap-2 px-4 h-9 rounded-full transition-all cursor-help"
                    :class="socketState.bridgeOnline 
                        ? 'bg-green-500/10 text-green-500' 
                        : 'bg-red-500/10 text-red-500 animate-pulse'"
                    v-tooltip.bottom="socketState.bridgeOnline ? 'Bridge Conectado (PC)' : 'Bridge OFFLINE (Verifique o iniciar.bat)'"
                >
                    <i class="fa-solid fa-server text-sm"></i>
                    <span class="text-[10px] font-black uppercase tracking-wider">
                        {{ socketState.bridgeOnline ? 'BRIDGE ON' : 'BRIDGE OFF' }}
                    </span>
                </div>

            </div>

            <div class="h-8 w-px bg-surface-3/50 mx-1 hidden md:block"></div>

            <Button icon="fa-solid fa-rotate" @click="fetchLogs" :loading="loading" class="!bg-surface-2 hover:!bg-surface-3 !border-none !text-surface-0 !rounded-full !w-10 !h-10" v-tooltip.bottom="'Atualizar Lista'" />
        </div>
    </div>

    <div class="flex-1 overflow-hidden p-6">
        <div class="h-full bg-surface-2 rounded-[24px] overflow-hidden flex flex-col border border-surface-3/10">
            <DataTable :value="logs" scrollable scrollHeight="flex" class="p-datatable-sm text-sm h-full"
                :pt="{
                    root: { class: 'bg-transparent' },
                    headerRow: { class: '!bg-surface-2 !border-b !border-surface-3/10' },
                    bodyRow: { class: '!bg-transparent hover:!bg-surface-3 !text-surface-300 transition-colors border-b border-surface-3/10' }
                }"
            >
                <Column field="created_at" header="Horário" class="w-24">
                    <template #body="{data}">
                        <span class="font-bold text-surface-200">{{ formatTime(data.created_at) }}</span>
                    </template>
                </Column>

                <Column field="type" header="Trabalho" class="w-32">
                    <template #body="{data}">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs"
                                 :class="getJobIconClass(data.type)">
                                <i :class="getJobIcon(data.type)"></i>
                            </div>
                            <span class="font-bold text-xs uppercase">{{ getJobLabel(data.type) }}</span>
                        </div>
                    </template>
                </Column>

                <Column field="service_type" header="Modalidade" class="w-32">
                    <template #body="{data}">
                        <Tag :value="getModalityLabel(data.service_type)" :severity="getModalitySeverity(data.service_type)" 
                             class="!text-[9px] !uppercase !font-black !px-2 !rounded-full" />
                    </template>
                </Column>

                <Column field="identifier" header="Cliente / Ref">
                    <template #body="{data}">
                        <span class="font-black text-surface-0 text-xs truncate block" :title="data.identifier">
                            {{ data.identifier }}
                        </span>
                    </template>
                </Column>

                <Column field="station" header="Destino" class="w-24">
                    <template #body="{data}">
                        <span class="text-xs font-bold text-surface-400 uppercase">{{ data.station }}</span>
                    </template>
                </Column>

                <Column field="status" header="Status" class="w-28">
                    <template #body="{data}">
                        <Tag :severity="getStatusSeverity(data.status)" :value="formatStatus(data.status)" class="!text-[9px] !font-black !rounded-full" />
                    </template>
                </Column>

                <Column header="Ações" class="w-24 text-right">
                    <template #body="{data}">
                        <div class="flex justify-end gap-1">
                            <Button 
                                icon="fa-solid fa-list-check" 
                                text rounded size="small" 
                                v-tooltip.top="'Reimprimir Itens Específicos'" 
                                @click="openSelectiveReprint(data)" 
                                class="!w-8 !h-8 !text-surface-400 hover:!text-primary-400 hover:!bg-surface-1" 
                            />
                            <Button 
                                icon="fa-solid fa-rotate-right" 
                                text rounded size="small" 
                                v-tooltip.top="'Reimprimir Tudo'" 
                                @click="handleReprint(data)" 
                                class="!w-8 !h-8 !text-surface-400 hover:!text-green-400 hover:!bg-surface-1" 
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </div>

    <PrintSelectionModal 
        v-model:visible="selectionModal.visible"
        :order="selectionModal.fakeOrder"
        :loading="selectionModal.loading"
        @confirm="handleSelectivePrint"
    />

  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import api from '@/services/api';
import { notify } from '@/services/notify';
import { socketState } from '@/services/socket'; 
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import PrintSelectionModal from '@/components/shared/PrintSelectionModal.vue';

const logs = ref([]);
const loading = ref(false);

const selectionModal = reactive({
    visible: false,
    loading: false,
    fakeOrder: null,
    originalLog: null
});

// --- UTILS ---
const formatTime = (isoString) => {
    if (!isoString) return '--:--';
    const date = new Date(isoString);
    return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
};

const getJobLabel = (type) => ({ 'kitchen': 'Cozinha', 'account': 'Conta', 'payment': 'Pagamento', 'delivery': 'Entrega', 'closing': 'Fechamento' }[type] || type);
const getJobIcon = (type) => `fa-solid ${({ 'kitchen': 'fa-fire-burner', 'account': 'fa-receipt', 'payment': 'fa-sack-dollar', 'delivery': 'fa-motorcycle', 'closing': 'fa-lock' }[type] || 'fa-print')}`;
const getJobIconClass = (type) => {
    if (type === 'kitchen') return 'bg-orange-500/20 text-orange-500';
    if (type === 'payment' || type === 'closing') return 'bg-green-500/20 text-green-500';
    return 'bg-blue-500/20 text-blue-500';
};

const getModalityLabel = (st) => {
    const map = { 
        'table': 'Mesa', 
        'delivery': 'Delivery', 
        'pickup': 'Retirada', 
        'counter': 'Balcão',
        'pdv': 'Balcão'
    };
    return map[st] || st || 'Geral';
};

const getModalitySeverity = (st) => {
    if (st === 'table') return 'info';
    if (st === 'delivery') return 'warning';
    if (st === 'pickup' || st === 'counter' || st === 'pdv') return 'secondary';
    return 'contrast';
};

const getStatusSeverity = (s) => { 
    if (s === 'printed') return 'success'; 
    if (s === 'local_error' || s === 'server_error') return 'danger'; 
    return 'warn'; 
};

const formatStatus = (s) => ({ 
    'printed': 'IMPRESSO', 
    'local_error': 'ERRO LOCAL', 
    'server_error': 'ERRO SERVER',
    'enviando': 'A CAMINHO', 
    'pending_server': 'NA FILA' 
}[s] || s.toUpperCase());

// --- ACTIONS ---

const fetchLogs = async () => { 
    loading.value = true; 
    try { 
        const { data } = await api.get('/print/logs'); 
        if (data.success) logs.value = data.logs; 
    } catch (e) { 
        console.error(e); 
    } finally { 
        loading.value = false; 
    } 
};

const handleReprint = async (log) => { 
    try { 
        await api.post('/print/add', { type: log.type, station: log.station, data: JSON.parse(log.payload) }); 
        notify('success', 'Reimpressão', 'Solicitação enviada.'); 
        setTimeout(fetchLogs, 1500); 
    } catch (e) { 
        notify('error', 'Erro', 'Falha ao reimprimir.'); 
    } 
};

const openSelectiveReprint = (log) => {
    try {
        const rawData = JSON.parse(log.payload);
        let items = [];

        if (log.type === 'kitchen') {
            items = rawData.tickets.map(t => ({
                name: t.name,
                qty: 1,
                modifiers: t.modifiers || [],
                notes: t.notes
            }));
        } else if (rawData.items) {
            items = rawData.items;
        } else {
            notify('warn', 'Indisponível', 'Este tipo de relatório não permite seleção de itens.');
            return;
        }

        selectionModal.fakeOrder = {
            id: rawData.orderId || log.identifier,
            items: items
        };
        selectionModal.originalLog = log;
        selectionModal.visible = true;

    } catch (e) {
        console.error(e);
        notify('error', 'Erro', 'Dados inválidos.');
    }
};

const handleSelectivePrint = async (partialOrder) => {
    selectionModal.loading = true;
    const log = selectionModal.originalLog;
    
    try {
        let newData = {};
        const originalPayload = JSON.parse(log.payload);
        
        const finalStation = partialOrder.targetStation || log.station;

        if (log.type === 'kitchen') {
            newData = {
                tickets: partialOrder.items.map(item => ({
                    ...item,
                    partIndex: 1, totalParts: 1,
                    table: originalPayload.tickets[0]?.table,
                    client: originalPayload.tickets[0]?.client,
                    orderId: originalPayload.tickets[0]?.orderId,
                    server_name: originalPayload.tickets[0]?.server_name,
                    sessionType: originalPayload.tickets[0]?.sessionType,
                    notes: (item.notes ? item.notes + ' ' : '') + '(REIMPRESSÃO)'
                }))
            };
        } else {
            newData = { ...originalPayload, items: partialOrder.items };
        }

        await api.post('/print/add', { 
            type: log.type, 
            station: finalStation, 
            data: newData 
        });

        notify('success', 'Reimpressão', `Enviado para ${finalStation.toUpperCase()}.`);
        selectionModal.visible = false;
        setTimeout(fetchLogs, 1500);

    } catch (e) {
        notify('error', 'Erro', 'Falha ao enviar.');
    } finally {
        selectionModal.loading = false;
    }
};

onMounted(fetchLogs);
</script>