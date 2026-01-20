<template>
  <div class="flex flex-col w-full h-full bg-surface-950 overflow-hidden">
    
    <div class="flex items-center justify-between px-6 py-5 shrink-0 bg-surface-950 z-20 border-b border-surface-800">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center">
                <i class="fa-solid fa-map mr-3 text-primary-500"></i> Mapa de Mesas
            </h1>
            <p class="text-surface-400 text-sm mt-1">Gerencie a ocupação e junte mesas conforme necessário.</p>
        </div>
        
        <div class="flex gap-3">
            <Button 
                v-if="!isSelectionMode"
                label="Juntar / Separar" 
                icon="fa-solid fa-link" 
                severity="secondary" 
                class="!bg-surface-800 !border-surface-700 !text-surface-300 hover:!text-white shadow-lg"
                @click="toggleSelectionMode"
            />
            
            <div v-else class="flex gap-2 animate-fade-in">
                <Button 
                    label="Cancelar" 
                    text 
                    class="!text-surface-400 hover:!text-white" 
                    @click="toggleSelectionMode"
                />
                <Button 
                    :label="`Unir (${selectedTables.length})`" 
                    icon="fa-solid fa-check" 
                    class="!bg-primary-500 hover:!bg-primary-400 !border-none !text-surface-900 font-bold shadow-lg shadow-primary-500/20"
                    :disabled="selectedTables.length < 2"
                    @click="confirmJoin"
                />
            </div>

            <Button icon="fa-solid fa-rotate" text rounded class="!text-surface-400 hover:!text-white" @click="store.fetchTables" v-tooltip="'Atualizar'" />
        </div>
    </div>

    <div class="flex-1 overflow-y-auto px-6 pb-20 scrollbar-thin">
        <div v-if="store.isLoading" class="flex justify-center p-20">
            <i class="fa-solid fa-spinner fa-spin text-4xl text-primary-500"></i>
        </div>

        <div v-else class="flex flex-col gap-8 pt-6">
            <div v-for="(tables, zoneName) in store.tablesByZone" :key="zoneName">
                <div class="flex items-center gap-4 mb-4 border-b border-surface-800 pb-2">
                    <h3 class="text-lg font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-layer-group text-primary-500 text-sm"></i>
                        {{ zoneName }}
                    </h3>
                    <span class="text-xs font-mono text-surface-500 bg-surface-900 px-2 py-1 rounded border border-surface-800">
                        {{ tables.length }} mesas
                    </span>
                </div>

                <div class="grid grid-cols-4 sm:grid-cols-5 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10 gap-3">
                    <div v-for="table in tables" :key="table.id"
                        class="relative h-24 rounded-xl border-2 flex flex-col items-center justify-center cursor-pointer select-none transition-all duration-200 active:scale-95 group"
                        :class="getTableClasses(table)"
                        @click="(event) => handleTableClick(event, table)"
                    >
                        <div v-if="isSelectionMode" class="absolute top-2 right-2 z-10">
                            <div class="w-5 h-5 rounded border flex items-center justify-center transition-colors"
                                :class="isSelected(table) ? 'bg-primary-500 border-primary-500 text-surface-900' : 'border-surface-600 bg-black/40'"
                            >
                                <i v-if="isSelected(table)" class="fa-solid fa-check text-[10px] font-bold"></i>
                            </div>
                        </div>

                        <span class="text-3xl font-black font-mono leading-none tracking-tighter"
                             :class="table.status === 'occupied' ? 'text-surface-950' : 'text-surface-300 group-hover:text-white'">
                            {{ table.number }}
                        </span>

                        <div v-if="table.status === 'occupied'" class="absolute bottom-2 flex flex-col items-center">
                            <span class="text-[10px] font-bold text-surface-900/70 uppercase tracking-widest">Ocupada</span>
                        </div>
                        <div v-else class="absolute bottom-2">
                            <span class="text-[10px] text-surface-600 group-hover:text-surface-400">Livre</span>
                        </div>
                        <div v-if="table.status === 'occupied'" class="absolute inset-0 rounded-xl bg-white/10 animate-pulse pointer-events-none"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <Popover ref="menuOp" class="!bg-surface-900 !border-surface-700 !shadow-2xl !rounded-xl">
        <div class="w-64 flex flex-col">
            <div class="p-3 border-b border-surface-800 flex justify-between items-center bg-surface-950/50 rounded-t-xl">
                <span class="font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-table"></i> Mesa {{ selectedTable?.number }}
                </span>
                <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded"
                      :class="selectedTable?.status === 'occupied' ? 'bg-primary-500 text-surface-900' : 'bg-surface-700 text-surface-400'">
                    {{ selectedTable?.status === 'occupied' ? 'Ocupada' : 'Livre' }}
                </span>
            </div>

            <div class="p-2 space-y-1">
                <button v-if="selectedTable?.status !== 'occupied'"
                    class="w-full text-left p-3 rounded-lg hover:bg-surface-800 flex items-center gap-3 transition-colors group"
                    @click="quickAction('open')"
                >
                    <div class="w-8 h-8 rounded-full bg-primary-500/20 text-primary-500 flex items-center justify-center group-hover:bg-primary-500 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-power-off"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-sm font-bold text-white">Abrir Mesa</span>
                        <span class="text-[10px] text-surface-400">Iniciar nova sessão</span>
                    </div>
                </button>

                <template v-else>
                    <button class="w-full text-left p-2 rounded-lg hover:bg-surface-800 flex items-center gap-3 transition-colors group"
                        @click="quickAction('pos_default')"
                    >
                        <i class="fa-solid fa-plus-circle text-primary-500 text-lg group-hover:scale-110 transition-transform"></i>
                        <span class="text-sm font-bold text-white">Novo Pedido</span>
                    </button>

                    <button class="w-full text-left p-2 rounded-lg hover:bg-surface-800 flex items-center gap-3 transition-colors group"
                        @click="emit('request-new-account', selectedTable)"
                    >
                        <i class="fa-solid fa-user-plus text-surface-400 text-lg group-hover:text-white"></i>
                        <span class="text-sm font-medium text-surface-300 group-hover:text-white">Nova Conta</span>
                    </button>

                    <div class="my-2 border-t border-b border-surface-800 py-2 max-h-40 overflow-y-auto scrollbar-thin">
                        <div v-if="isLoadingAccounts" class="flex justify-center py-2">
                            <i class="fa-solid fa-spinner fa-spin text-surface-500"></i>
                        </div>
                        <div v-else-if="menuAccounts.length === 0" class="text-center text-xs text-surface-500 py-2">
                            Apenas conta Principal
                        </div>
                        <div v-else class="flex flex-col gap-1">
                            <span class="text-[10px] uppercase font-bold text-surface-500 px-2 mb-1">Contas Abertas</span>
                            <button v-for="acc in menuAccounts" :key="acc"
                                class="w-full text-left px-2 py-1.5 rounded hover:bg-surface-800 flex items-center justify-between group"
                                @click="quickAction('pos_account', acc)"
                            >
                                <div class="flex items-center gap-2">
                                    <div class="w-5 h-5 rounded-full bg-surface-700 text-[10px] flex items-center justify-center text-surface-300 font-bold">
                                        {{ acc.substring(0,2).toUpperCase() }}
                                    </div>
                                    <span class="text-sm text-surface-200 group-hover:text-white">{{ acc }}</span>
                                </div>
                                <i class="fa-solid fa-chevron-right text-[10px] text-surface-600 group-hover:text-primary-500"></i>
                            </button>
                        </div>
                    </div>

                    <button class="w-full text-left p-2 rounded-lg hover:bg-surface-800 flex items-center gap-3 transition-colors group hover:bg-red-500/10"
                        @click="openSwapModal"
                    >
                        <i class="fa-solid fa-right-left text-surface-400 group-hover:text-red-400 text-sm"></i>
                        <span class="text-sm font-medium text-surface-400 group-hover:text-red-400">Trocar de Mesa</span>
                    </button>
                </template>
            </div>
        </div>
    </Popover>

    <SwapTableModal 
        v-model:visible="showSwapModal" 
        :source-table="selectedTable"
        :accounts="menuAccounts"
        @success="store.fetchTables"
    />

  </div>
</template>

<script setup>
import { ref, onMounted, onActivated } from 'vue'; 
import { useRouter } from 'vue-router';
import { useTablesStore } from '@/stores/tables-store';
import { useSessionStore } from '@/stores/session-store';
import api from '@/services/api';
import { notify } from '@/services/notify';
import Button from 'primevue/button';
import Popover from 'primevue/popover'; 
import SwapTableModal from './SwapTableModal.vue';

const router = useRouter();
const store = useTablesStore();
const sessionStore = useSessionStore();
const emit = defineEmits(['request-new-account']); 

// Estado
const menuOp = ref(null);
const selectedTable = ref(null);
const menuAccounts = ref([]);
const isLoadingAccounts = ref(false);
const showSwapModal = ref(false);

const isSelectionMode = ref(false);
const selectedTables = ref([]);

// Estilos dinâmicos do Card da Mesa
const getTableClasses = (table) => {
    if (isSelectionMode.value && isSelected(table)) {
        return 'bg-primary-500/20 border-primary-500 text-primary-400 ring-2 ring-primary-500/50';
    }
    if (table.status === 'occupied') {
        return 'bg-primary-600 border-primary-500 text-surface-950 shadow-xl shadow-primary-900/40 hover:bg-primary-500 hover:scale-105';
    }
    return 'bg-surface-900 border-surface-800 text-surface-400 hover:bg-surface-800 hover:text-white hover:border-surface-600 hover:-translate-y-1';
};

// Handlers
const handleTableClick = (event, table) => {
    if (isSelectionMode.value) {
        toggleSelection(table);
    } else {
        toggleMenu(event, table);
    }
};

const toggleMenu = async (event, table) => {
    selectedTable.value = table;
    menuOp.value.toggle(event);

    if (table.status === 'occupied' && table.sessionId) {
        isLoadingAccounts.value = true;
        menuAccounts.value = [];
        try {
            const res = await api.get(`/session/${table.sessionId}`);
            if (res.data.success && res.data.session.accounts) {
                menuAccounts.value = res.data.session.accounts.map(a => a.name); 
            }
        } catch (e) {
            console.error('Erro ao carregar contas do menu', e);
        } finally {
            isLoadingAccounts.value = false;
        }
    }
};

const quickAction = (action, payload = null) => {
    sessionStore.sessionType = 'table';
    sessionStore.identifier = selectedTable.value.number;
    sessionStore.sessionId = selectedTable.value.sessionId || null;

    if (action === 'open') {
        sessionStore.setAccount('Principal');
        router.push('/pdv');
    } 
    else if (action === 'pos_default') {
        sessionStore.setAccount('Principal');
        router.push('/pdv');
    } 
    else if (action === 'pos_account') {
        sessionStore.setAccount(payload);
        router.push('/pdv');
    }
    
    if (menuOp.value) menuOp.value.hide();
};

const openSwapModal = () => {
    showSwapModal.value = true;
    if (menuOp.value) menuOp.value.hide();
};

// Seleção Múltipla
const toggleSelectionMode = () => {
    isSelectionMode.value = !isSelectionMode.value;
    selectedTables.value = [];
};

const isSelected = (table) => selectedTables.value.some(t => t.id === table.id);

const toggleSelection = (table) => {
    const idx = selectedTables.value.findIndex(t => t.id === table.id);
    if (idx > -1) selectedTables.value.splice(idx, 1);
    else selectedTables.value.push(table);
};

const confirmJoin = () => {
    const numbers = selectedTables.value.map(t => t.number).join(', ');
    notify('success', 'Mesas Unidas', `Mesas ${numbers} agrupadas.`);
    toggleSelectionMode();
};

onMounted(() => { store.fetchTables(); });
onActivated(() => { store.fetchTables(); });
</script>