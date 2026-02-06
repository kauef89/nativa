<template>
  <div class="flex flex-col w-full h-full bg-surface-1 overflow-hidden">
    
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between px-6 shrink-0 z-20 border-b border-surface-3/10 min-h-20 py-4 gap-4 bg-surface-1">
      <div>
        <h1 class="text-2xl font-black text-surface-0 flex items-center">
          <i class="fa-solid fa-map mr-3 text-primary-500"></i> Mapa de Mesas
        </h1>
        <p class="text-surface-400 text-sm mt-1 font-bold">Visão geral da ocupação.</p>
      </div>
      
      <div class="flex gap-2 w-full md:w-auto justify-end">
        <Button 
            v-if="!isSelectionMode"
            label="Juntar / Separar" 
            icon="fa-solid fa-link" 
            class="!bg-surface-2 hover:!bg-surface-3 !border-none !text-surface-300 hover:!text-surface-0 !font-bold !rounded-full !flex-1 md:!flex-none !whitespace-nowrap"
            @click="toggleSelectionMode"
        />
        
        <div v-else class="flex gap-2 animate-fade-in w-full md:w-auto">
            <Button label="Cancelar" text class="!text-surface-400 hover:!text-surface-0 !font-bold !flex-1 md:!flex-none" @click="toggleSelectionMode" />
            <Button :label="`Unir (${selectedTables.length})`" icon="fa-solid fa-check" class="!bg-primary-500 hover:!bg-primary-400 !border-none !text-surface-950 font-black !rounded-full shadow-lg !flex-1 md:!flex-none" :disabled="selectedTables.length < 2" @click="confirmJoin" />
        </div>

        <Button icon="fa-solid fa-rotate" text rounded class="!text-surface-400 hover:!text-surface-0 shrink-0" @click="store.fetchTables" v-tooltip="'Atualizar'" />
      </div>
    </div>

    <div class="flex-1 overflow-y-auto px-3 md:px-6 pb-20 scrollbar-thin bg-surface-2 relative">
      <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-[0.02] pointer-events-none mix-blend-overlay"></div>

      <div v-if="store.isLoading" class="flex justify-center p-20 relative z-10">
        <LoadingSpinner size="large" label="Carregando Mesas..." />
      </div>

      <div v-else class="flex flex-col gap-8 pt-8 relative z-10">
        <div v-for="(tables, zoneName) in store.tablesByZone" :key="zoneName">
          
          <div class="flex items-center gap-3 mb-4 border-b border-surface-3/10 pb-2">
            <h3 class="text-sm font-black text-surface-0 uppercase tracking-wider flex items-center gap-2">
              <span class="w-1.5 h-4 bg-primary-500 rounded-full shadow-[0_0_10px_rgba(16,185,129,0.5)]"></span>
              {{ zoneName }}
            </h3>
            <span class="text-[9px] font-bold text-surface-400 bg-surface-3 px-2 py-0.5 rounded-full uppercase tracking-wider shadow-sm">{{ tables.length }} mesas</span>
          </div>

          <div class="grid grid-cols-4 md:grid-cols-[repeat(auto-fill,minmax(120px,1fr))] gap-2 md:gap-3">
            <div v-for="table in tables" :key="table.id" 
                 class="relative h-24 rounded-[24px] flex flex-col items-center justify-center cursor-pointer select-none transition-all duration-300 active:scale-95 group shadow-sm border border-transparent overflow-hidden"
                 :class="getTableClasses(table)" 
                 @click="(event) => handleTableClick(event, table)"
            >
              <div v-if="isSelectionMode" class="absolute top-2 right-2 z-20">
                  <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors"
                       :class="isSelected(table) ? 'bg-white border-white text-primary-500' : 'border-white/30 bg-black/20'">
                      <i v-if="isSelected(table)" class="fa-solid fa-check text-[10px] font-black"></i>
                  </div>
              </div>

              <div v-if="!table.is_active" class="absolute inset-0 bg-surface-1/80 z-10 flex items-center justify-center backdrop-blur-[1px]">
                  <i class="fa-solid fa-ban text-surface-500 text-lg"></i>
              </div>

              <div class="z-0 flex flex-col items-center gap-1.5">
                  <span class="text-3xl font-black leading-none tracking-tighter transition-transform group-hover:scale-110" 
                        :class="table.status === 'occupied' ? 'text-surface-950' : 'text-surface-200 group-hover:text-surface-0'">
                    {{ table.number }}
                  </span>
                  
                  <div class="px-2.5 py-1 rounded-full text-[8px] font-black uppercase tracking-widest transition-colors backdrop-blur-md"
                       :class="getStatusClasses(table)">
                      {{ getStatusLabel(table) }}
                  </div>
              </div>

              <div v-if="table.status === 'occupied' && table.is_active" class="absolute inset-0 bg-gradient-to-br from-white/20 to-transparent opacity-100 pointer-events-none"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <Popover ref="menuOp" class="!bg-surface-2 !border-none !shadow-2xl !rounded-[24px]">
        <div class="w-72 p-2">
            <TableActionsList 
                :table="selectedTable" 
                :accounts="menuAccounts" 
                :loading="isLoadingAccounts" 
                :can-void="canTableBeVoided"
                :is-mobile="false"
                @action="handleAction" 
            />
        </div>
    </Popover>

    <BottomSheet 
        v-model:visible="showMobileSheet"
    >
        <TableActionsList 
            :table="selectedTable" 
            :accounts="menuAccounts" 
            :loading="isLoadingAccounts" 
            :can-void="canTableBeVoided"
            :is-mobile="true"
            @action="handleAction" 
        />
    </BottomSheet>

    <SwapTableModal v-model:visible="showSwapModal" :source-table="selectedTable" :accounts="menuAccounts.map((a) => a.name)" @success="store.fetchTables" />
  </div>
</template>

<script setup>
import { ref, onMounted, onActivated } from "vue";
import { useRouter } from "vue-router";
import { useTablesStore } from "@/stores/tables-store";
import { useSessionStore } from "@/stores/session-store";
import { useFormat } from '@/composables/useFormat';
import { useMobile } from "@/composables/useMobile";
import api from "@/services/api";
import { notify } from "@/services/notify";
import Button from "primevue/button";
import Popover from "primevue/popover";
import SwapTableModal from "./SwapTableModal.vue";
import LoadingSpinner from "@/components/shared/LoadingSpinner.vue";
import BottomSheet from "@/components/shared/BottomSheet.vue";
import TableActionsList from "./TableActionsList.vue";

const router = useRouter();
const store = useTablesStore();
const sessionStore = useSessionStore();
const { formatCurrency } = useFormat();
const { isMobile } = useMobile();
const emit = defineEmits(["request-new-account"]);

const menuOp = ref(null);
const showMobileSheet = ref(false);
const selectedTable = ref(null);
const menuAccounts = ref([]);
const isLoadingAccounts = ref(false);
const showSwapModal = ref(false);
const canTableBeVoided = ref(false);

const isSelectionMode = ref(false);
const selectedTables = ref([]);

const getTableClasses = (table) => {
  if (isSelectionMode.value && isSelected(table)) {
    return "bg-primary-500/20 border-primary-500 text-primary-400 ring-2 ring-primary-500/50 scale-95";
  }
  if (!table.is_active) {
    return "bg-surface-3 border-transparent opacity-40 grayscale";
  }
  if (table.status === "occupied") {
    return "bg-primary-500 text-surface-950 shadow-lg shadow-primary-500/30 hover:scale-[1.02]";
  }
  return "bg-surface-3 hover:bg-surface-4 text-surface-400 hover:text-surface-0 hover:-translate-y-1 shadow-sm";
};

const getStatusClasses = (table) => {
    if (!table.is_active) return "text-surface-600 bg-surface-2/50";
    if (table.status === "occupied") return "text-surface-900 bg-surface-950/10 border border-surface-950/10";
    return "text-surface-500 bg-surface-2 border border-surface-4/20 group-hover:text-surface-300 group-hover:border-surface-4/50";
};

const getStatusLabel = (table) => {
    if (!table.is_active) return "Inativa";
    if (table.status === "occupied") return "Ocupada";
    return "Livre";
};

const handleTableClick = (event, table) => {
    if (isSelectionMode.value) {
        toggleSelection(table);
    } else {
        selectedTable.value = table;
        loadTableData(table);

        if (isMobile.value) {
            showMobileSheet.value = true;
        } else {
            menuOp.value.toggle(event);
        }
    }
};

const loadTableData = async (table) => {
    canTableBeVoided.value = false;
    menuAccounts.value = [];
    
    if (table.status === "occupied" && table.sessionId) {
        isLoadingAccounts.value = true;
        try {
            const res = await api.get(`/session-items?session_id=${table.sessionId}`);
            if (res.data.success) {
                const grouped = res.data.grouped_items || {};
                const accountsList = res.data.accounts || ["Principal"];
                const calculatedAccounts = accountsList.map((accName) => {
                    const items = grouped[accName] || [];
                    const total = items.reduce((sum, item) => item.status === 'paid' ? sum : sum + parseFloat(item.line_total), 0);
                    return { name: accName, total: total };
                });
                menuAccounts.value = calculatedAccounts;
                canTableBeVoided.value = parseFloat(res.data.total) <= 0.01;
            }
        } catch (e) { console.error(e); } 
        finally { isLoadingAccounts.value = false; }
    }
};

const handleAction = ({ type, payload, mode }) => {
    if (menuOp.value) menuOp.value.hide();
    showMobileSheet.value = false;

    if (type === 'open' || type === 'pos_default') {
        quickAction('open');
    } else if (type === 'new_account') {
        emit("request-new-account", selectedTable.value);
    } else if (type === 'pos_account') {
        quickAction('pos_account', payload, mode);
    } else if (type === 'swap') {
        showSwapModal.value = true;
    } else if (type === 'void') {
        handleLiberarMesa();
    } else if (type === 'toggle_active') {
        toggleTableActive(payload);
    }
};

const toggleTableActive = async (table) => {
    try {
        await api.post('/tables/toggle-active', { table_id: table.id, is_active: !table.is_active });
        notify('success', 'Atualizado', `Mesa ${table.number} ${!table.is_active ? 'Ativada' : 'Inativada'}.`);
        store.fetchTables();
    } catch (e) {
        notify('error', 'Erro', 'Falha ao alterar status.');
    }
};

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

const handleLiberarMesa = async () => {
  if (!confirm(`Deseja LIBERAR a Mesa ${selectedTable.value.number}?`)) return;
  try {
    await api.post("/void-session", { session_id: selectedTable.value.sessionId });
    notify("info", "Mesa Liberada", "Sessão encerrada.");
    store.fetchTables();
  } catch (e) { notify("error", "Erro", "Falha ao liberar."); }
};

const quickAction = (action, payload = null, mode = 'order') => {
  sessionStore.sessionType = "table";
  sessionStore.identifier = selectedTable.value.number;
  sessionStore.sessionId = selectedTable.value.sessionId || null;

  if (menuAccounts.value.length > 0) {
      const accountNames = menuAccounts.value.map(a => a.name);
      if (!accountNames.includes('Principal')) accountNames.unshift('Principal');
      sessionStore.accounts = accountNames;
  }

  if (action === "open" || action === "pos_default") {
    sessionStore.setAccount("Principal");
    router.push("/staff/pdv");
  } else if (action === "pos_account") {
    sessionStore.setAccount(payload); 
    if (mode === 'checkout') {
        router.push({ path: "/staff/pdv", query: { mode: 'checkout' } });
    } else {
        router.push("/staff/pdv");
    }
  }
};

onMounted(() => { store.fetchTables(); });
onActivated(() => { store.fetchTables(); });
</script>