<template>
  <Dialog 
    :visible="visible" 
    @update:visible="emit('update:visible', $event)"
    modal 
    header="Trocar Mesa" 
    :style="{ width: '450px' }" 
    :pt="{
        root: { class: '!bg-surface-900 !border !border-surface-800' },
        header: { class: '!bg-surface-950 !text-white' },
        content: { class: '!bg-surface-900 !p-0' }
    }"
  >
    <div class="flex flex-col h-[600px]">
        
        <div class="p-4 bg-surface-950 border-b border-surface-800 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <span class="text-surface-400 text-sm">Origem:</span>
                <div class="px-3 py-1 bg-surface-800 rounded-lg text-white font-bold border border-surface-700">
                    Mesa {{ sourceTable?.number }}
                </div>
            </div>
            <div class="text-primary-500 animate-pulse">
                <i class="fa-solid fa-arrow-right-long text-xl"></i>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-surface-400 text-sm">Destino:</span>
                <div class="px-3 py-1 rounded-lg font-bold border transition-all"
                     :class="targetTableNumber ? 'bg-primary-500 text-surface-950 border-primary-400' : 'bg-surface-800 text-surface-500 border-surface-700 border-dashed'">
                    {{ targetTableNumber ? 'Mesa ' + targetTableNumber : 'Selecione...' }}
                </div>
            </div>
        </div>

        <div class="flex-1 flex flex-col overflow-hidden">
            
            <div class="p-4 border-b border-surface-800 shrink-0 bg-surface-900">
                <p class="text-xs font-bold text-surface-500 uppercase mb-3 px-1">O que você deseja mover?</p>
                <div class="flex flex-wrap gap-2">
                    
                    <button 
                        class="px-4 py-2 rounded-lg border text-sm font-bold flex items-center gap-2 transition-all"
                        :class="transferScope === 'all' 
                            ? 'bg-primary-500/20 border-primary-500 text-primary-400 ring-2 ring-primary-500/30' 
                            : 'bg-surface-800 border-surface-700 text-surface-300 hover:bg-surface-700 hover:text-white'"
                        @click="setTransferScope('all')"
                    >
                        <i class="fa-solid fa-layer-group"></i> Toda a Mesa
                    </button>

                    <button v-for="acc in accounts" :key="acc"
                        class="px-4 py-2 rounded-lg border text-sm font-bold flex items-center gap-2 transition-all"
                        :class="(transferScope === 'account' && selectedTransferAccount === acc)
                            ? 'bg-white text-surface-950 border-white shadow-lg' 
                            : 'bg-surface-800 border-surface-700 text-surface-300 hover:bg-surface-700 hover:text-white'"
                        @click="setTransferScope('account', acc)"
                    >
                        <span class="w-5 h-5 rounded-full bg-surface-600 text-white text-[9px] flex items-center justify-center font-bold" 
                              :class="{'!bg-surface-950 !text-white': transferScope === 'account' && selectedTransferAccount === acc}">
                            {{ acc.substring(0,2).toUpperCase() }}
                        </span>
                        {{ acc }}
                    </button>

                </div>
            </div>

            <div class="flex-1 overflow-hidden bg-surface-900 flex flex-col">
                <p class="text-xs font-bold text-surface-500 uppercase p-4 pb-2 shrink-0">Para qual mesa livre?</p>
                
                <div v-if="Object.keys(availableTablesByZone).length === 0" class="flex flex-col items-center justify-center h-40 text-surface-500">
                    <i class="fa-solid fa-ban text-2xl mb-2 opacity-50"></i>
                    <span>Nenhuma mesa livre.</span>
                </div>

                <div v-else class="flex-1 overflow-x-auto overflow-y-auto p-4 pt-0 flex flex-row gap-6 scrollbar-thin">
                    <div v-for="(tables, zone) in availableTablesByZone" :key="zone" class="flex flex-col min-w-[180px]">
                        <div class="sticky top-0 bg-surface-900 py-2 mb-3 border-b border-surface-800 z-10">
                            <h4 class="text-[10px] font-black text-primary-500 uppercase tracking-widest pl-1">{{ zone }}</h4>
                        </div>

                        <div class="grid grid-cols-3 gap-2 pb-4">
                            <div v-for="t in tables" :key="t.value" 
                                class="cursor-pointer rounded-lg border flex items-center justify-center h-12 w-12 transition-all hover:scale-105 active:scale-95"
                                :class="targetTableNumber === t.value 
                                    ? 'bg-primary-500 border-primary-400 text-surface-900 shadow-md font-black' 
                                    : 'bg-surface-950 border-surface-800 text-surface-300 hover:border-surface-600 hover:text-white hover:bg-surface-800 font-bold'"
                                @click="targetTableNumber = t.value"
                            >
                                <span class="text-lg leading-none font-mono">{{ t.value }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="p-4 border-t border-surface-800 bg-surface-950 flex justify-end gap-3 shrink-0">
            <Button label="Cancelar" text class="!text-surface-400 hover:!text-white" @click="close" />
            <Button 
                :label="transferScope === 'all' ? 'Transferir Mesa' : `Mover conta ${selectedTransferAccount}`" 
                icon="fa-solid fa-check" 
                class="!bg-primary-600 hover:!bg-primary-500 !border-none !font-bold shadow-lg shadow-primary-900/20" 
                @click="handleSwapTable" 
                :loading="isSwapping" 
                :disabled="!targetTableNumber"
            />
        </div>
    </div>
  </Dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useTablesStore } from '@/stores/tables-store';
import api from '@/services/api';
import { notify } from '@/services/notify';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';

const props = defineProps(['visible', 'sourceTable', 'accounts']);
const emit = defineEmits(['update:visible', 'success']);

const store = useTablesStore();

const targetTableNumber = ref(null);
const isSwapping = ref(false);
const transferScope = ref('all'); 
const selectedTransferAccount = ref(null);

watch(() => props.visible, (newVal) => {
    if (newVal) {
        targetTableNumber.value = null;
        transferScope.value = 'all';
        selectedTransferAccount.value = null;
    }
});

const availableTablesByZone = computed(() => {
    const groups = {};
    for (const zone in store.tablesByZone) {
        const tablesInZone = store.tablesByZone[zone].filter(table => 
            table.status !== 'occupied' && table.id !== props.sourceTable?.id
        ).map(table => ({
            value: table.number,
            label: table.number
        })).sort((a, b) => a.value - b.value);

        if (tablesInZone.length > 0) {
            groups[zone] = tablesInZone;
        }
    }
    return groups;
});

const setTransferScope = (scope, account = null) => {
    transferScope.value = scope;
    selectedTransferAccount.value = (scope === 'all') ? null : account;
};

const handleSwapTable = async () => {
    if (!targetTableNumber.value || !props.sourceTable) return;
    
    isSwapping.value = true;
    try {
        const payload = { 
            from_number: props.sourceTable.number, 
            to_number: targetTableNumber.value,
            account: transferScope.value === 'account' ? selectedTransferAccount.value : null
        };

        await api.post('/swap-table', payload);
        
        notify('success', 'Sucesso', 'Transferência realizada com sucesso.');
        emit('success'); 
        close();
        
    } catch (e) {
        console.error(e);
        const msg = e.response?.data?.message || 'Falha ao trocar mesa.';
        notify('error', 'Erro', msg);
    } finally {
        isSwapping.value = false;
    }
};

const close = () => {
    emit('update:visible', false);
};
</script>

<style scoped>
/* Personalização fina da scrollbar para não poluir o visual escuro */
.scrollbar-thin::-webkit-scrollbar {
  height: 6px;
  width: 6px;
}
.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
  background: #334155;
  border-radius: 10px;
}
.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background: #475569;
}
</style>