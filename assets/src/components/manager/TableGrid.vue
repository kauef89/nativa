<template>
  <div class="flex flex-col w-full h-full bg-surface-950 overflow-hidden">
    <div
      class="flex items-center justify-between px-6 py-5 shrink-0 bg-surface-950 z-20 border-b border-surface-800"
    >
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center">
          <i class="fa-solid fa-map mr-3 text-primary-500"></i> Mapa de Mesas
        </h1>
        <p class="text-surface-400 text-sm mt-1">
          Gerencie a ocupação e disponibilidade das mesas.
        </p>
      </div>

      <div class="flex gap-3">
        <Button
          icon="fa-solid fa-rotate"
          text
          rounded
          class="!text-surface-400 hover:!text-white"
          @click="store.fetchTables"
          v-tooltip="'Atualizar'"
        />
      </div>
    </div>

    <div class="flex-1 overflow-y-auto px-6 pb-20 scrollbar-thin">
      <div v-if="store.isLoading" class="flex justify-center p-20">
        <i class="fa-solid fa-spinner fa-spin text-4xl text-primary-500"></i>
      </div>

      <div v-else class="flex flex-col gap-8 pt-6">
        <div v-for="(tables, zoneName) in store.tablesByZone" :key="zoneName">
          <div
            class="flex items-center gap-4 mb-4 border-b border-surface-800 pb-2"
          >
            <h3
              class="text-lg font-bold text-white uppercase tracking-wider flex items-center gap-2"
            >
              <i class="fa-solid fa-layer-group text-primary-500 text-sm"></i>
              {{ zoneName }}
            </h3>
            <span
              class="text-xs font-mono text-surface-500 bg-surface-900 px-2 py-1 rounded border border-surface-800"
            >
              {{ tables.length }} mesas
            </span>
          </div>

          <div
            class="grid grid-cols-4 sm:grid-cols-5 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10 gap-3"
          >
            <div
              v-for="table in tables"
              :key="table.id"
              class="relative h-24 rounded-xl border-2 flex flex-col items-center justify-center cursor-pointer select-none transition-all duration-200 active:scale-95 group"
              :class="getTableClasses(table)"
              @click="(event) => handleTableClick(event, table)"
            >
              <i
                v-if="!table.is_active"
                class="fa-solid fa-ban absolute top-2 right-2 text-[10px] opacity-50"
              ></i>

              <span
                class="text-3xl font-black font-mono leading-none tracking-tighter mb-1"
                :class="
                  table.status === 'occupied'
                    ? 'text-surface-950'
                    : table.is_active
                      ? 'text-surface-300 group-hover:text-white'
                      : 'text-surface-600'
                "
              >
                {{ table.number }}
              </span>

              <div class="flex flex-col items-center">
                <span
                  v-if="!table.is_active"
                  class="text-[9px] text-surface-600 font-bold uppercase tracking-widest"
                  >Desativada</span
                >

                <span
                  v-else-if="table.status === 'occupied'"
                  class="text-[11px] font-black text-surface-900 bg-white/20 px-2 py-0.5 rounded-md backdrop-blur-sm shadow-sm font-mono"
                >
                  R$ {{ formatMoney(table.total) }}
                </span>

                <span
                  v-else
                  class="text-[10px] text-surface-600 group-hover:text-surface-400 uppercase tracking-widest"
                  >LIVRE</span
                >
              </div>

              <div
                v-if="table.status === 'occupied' && table.is_active"
                class="absolute inset-0 rounded-xl bg-white/10 animate-pulse pointer-events-none"
              ></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <Popover
      ref="menuOp"
      class="!bg-surface-900 !border-surface-700 !shadow-2xl !rounded-xl"
    >
      <div class="w-72 flex flex-col p-2 gap-1">
        <div
          class="p-3 border-b border-surface-800 flex justify-between items-center bg-surface-950/50 rounded-t-xl mb-1"
        >
          <span class="font-bold text-white flex items-center gap-2 text-lg">
            <i class="fa-solid fa-table"></i> Mesa {{ selectedTable?.number }}
          </span>
          <span
            v-if="selectedTable?.status === 'occupied'"
            class="text-xs font-mono font-bold text-green-400"
          >
            Total: R$ {{ formatMoney(selectedTable?.total) }}
          </span>
        </div>

        <template v-if="!selectedTable?.is_active">
          <button
            class="w-full text-left p-3 rounded-lg hover:bg-green-500/10 flex items-center gap-3 group transition-colors"
            @click="toggleTableStatus(true)"
          >
            <i class="fa-solid fa-check-circle text-green-500 text-lg"></i>
            <span class="text-sm font-bold text-white">Reativar Mesa</span>
          </button>
        </template>

        <template v-else>
          <template v-if="selectedTable?.status === 'occupied'">
            <button
              class="w-full text-left p-3 rounded-lg bg-primary-600 hover:bg-primary-500 flex items-center justify-center gap-2 transition-colors group mb-2 shadow-lg shadow-primary-900/20"
              @click="quickAction('pos_account', 'Principal')"
            >
              <i class="fa-solid fa-plus-circle text-surface-900 font-bold"></i>
              <span class="text-sm font-black text-surface-900 uppercase"
                >Novo Pedido</span
              >
            </button>

            <div
              class="my-1 border-t border-b border-surface-800 py-2 max-h-56 overflow-y-auto scrollbar-thin"
            >
              <div v-if="isLoadingAccounts" class="flex justify-center py-4">
                <i class="fa-solid fa-spinner fa-spin text-surface-500"></i>
              </div>

              <div v-else class="flex flex-col gap-1.5 px-1">
                <span
                  class="text-[10px] uppercase font-bold text-surface-500 mb-1 flex justify-between px-1"
                >
                  <span>Contas em Aberto</span>
                  <span>Valor</span>
                </span>

                <div v-if="menuAccounts.length === 0" class="text-center py-4 text-surface-500 text-xs italic">
                    Nenhuma conta com débito.
                </div>

                <div
                  v-for="acc in menuAccounts"
                  :key="acc.name"
                  class="flex gap-1 group"
                >
                  <button
                    class="flex-1 text-left px-3 py-2 rounded-lg bg-surface-800 hover:bg-surface-700 flex items-center justify-between border border-surface-700 hover:border-surface-600 transition-all"
                    @click="quickAction('payment_account', acc.name)"
                  >
                    <div class="flex items-center gap-2 overflow-hidden">
                      <div
                        class="w-6 h-6 rounded-full bg-surface-900 text-[10px] flex items-center justify-center text-surface-400 font-bold border border-surface-700 shrink-0"
                      >
                        {{ acc.name.substring(0, 2).toUpperCase() }}
                      </div>
                      <span class="text-sm font-bold text-white truncate">{{
                        acc.name
                      }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="text-xs font-mono font-bold text-green-400"
                        >R$ {{ formatMoney(acc.total) }}</span
                      >
                      <i
                        class="fa-solid fa-chevron-right text-[10px] text-surface-600"
                      ></i>
                    </div>
                  </button>

                  <button
                    class="w-10 rounded-lg bg-surface-800 hover:bg-primary-500/20 border border-surface-700 hover:border-primary-500 text-surface-400 hover:text-primary-400 flex items-center justify-center transition-all"
                    @click.stop="quickAction('pos_account', acc.name)"
                    v-tooltip.top="'Adicionar item para ' + acc.name"
                  >
                    <i class="fa-solid fa-plus text-xs"></i>
                  </button>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-2 mt-2">
              <button
                v-if="canTableBeVoided"
                class="text-left p-3 rounded-lg bg-surface-800 border border-surface-700 hover:border-red-500/30 hover:bg-red-500/10 flex items-center justify-center gap-2 transition-colors group w-full"
                @click="handleLiberarMesa"
              >
                <i class="fa-solid fa-lock-open text-orange-400 group-hover:text-red-400"></i>
                <span
                  class="text-xs font-bold text-surface-400 group-hover:text-red-400 uppercase tracking-wider"
                  >Liberar Mesa</span
                >
              </button>
            </div>
          </template>

          <template v-else>
            <button
              class="w-full text-left p-3 rounded-lg bg-surface-800 hover:bg-primary-600 flex items-center gap-3 group transition-all shadow-md"
              @click="quickAction('open')"
            >
              <div
                class="w-8 h-8 rounded-full bg-surface-900/50 flex items-center justify-center text-white group-hover:text-white transition-colors"
              >
                <i class="fa-solid fa-power-off"></i>
              </div>
              <div class="flex flex-col">
                <span class="text-sm font-bold text-white">Abrir Mesa</span>
                <span
                  class="text-[10px] text-surface-400 group-hover:text-surface-200"
                  >Iniciar nova sessão</span
                >
              </div>
            </button>

            <div class="border-t border-surface-800 mt-2 pt-2">
              <button
                class="w-full text-left p-2 rounded-lg hover:bg-red-500/10 flex items-center gap-3 group transition-colors"
                @click="toggleTableStatus(false)"
              >
                <i
                  class="fa-solid fa-ban text-surface-500 group-hover:text-red-400"
                ></i>
                <span
                  class="text-xs font-bold text-surface-400 group-hover:text-red-400"
                  >Desativar Mesa</span
                >
              </button>
            </div>
          </template>
        </template>
      </div>
    </Popover>

    <SwapTableModal
      v-model:visible="showSwapModal"
      :source-table="selectedTable"
      :accounts="menuAccounts.map((a) => a.name)"
      @success="store.fetchTables"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, onActivated } from "vue";
import { useRouter } from "vue-router";
import { useTablesStore } from "@/stores/tables-store";
import { useSessionStore } from "@/stores/session-store";
import api from "@/services/api";
import { notify } from "@/services/notify";
import Button from "primevue/button";
import Popover from "primevue/popover";
import SwapTableModal from "./SwapTableModal.vue";

const router = useRouter();
const store = useTablesStore();
const sessionStore = useSessionStore();

const emit = defineEmits(["request-new-account"]);

const menuOp = ref(null);
const selectedTable = ref(null);
const menuAccounts = ref([]);
const isLoadingAccounts = ref(false);
const showSwapModal = ref(false);
const canTableBeVoided = ref(false);

const formatMoney = (val) =>
  parseFloat(val || 0)
    .toFixed(2)
    .replace(".", ",");

const getTableClasses = (table) => {
  if (!table.is_active) {
    return "bg-surface-950 border-surface-900 opacity-40 grayscale pointer-events-auto cursor-pointer";
  }
  if (table.status === "occupied") {
    return "bg-primary-600 border-primary-500 text-surface-950 shadow-xl shadow-primary-900/40 hover:bg-primary-500 hover:scale-105";
  }
  return "bg-surface-900 border-surface-800 text-surface-400 hover:bg-surface-800 hover:text-white hover:border-surface-600 hover:-translate-y-1";
};

const handleTableClick = (event, table) => {
  toggleMenu(event, table);
};

const toggleMenu = async (event, table) => {
  selectedTable.value = table;
  menuOp.value.toggle(event);
  canTableBeVoided.value = false;

  if (table.status === "occupied" && table.sessionId) {
    isLoadingAccounts.value = true;
    menuAccounts.value = [];
    try {
      const res = await api.get(`/session-items?session_id=${table.sessionId}`);
      if (res.data.success) {
        const grouped = res.data.grouped_items || {};
        const accountsList = res.data.accounts || ["Principal"];

        const calculatedAccounts = accountsList.map((accName) => {
          const items = grouped[accName] || [];
          const total = items.reduce(
            (sum, item) => sum + parseFloat(item.line_total),
            0,
          );
          return { name: accName, total: total };
        });

        // --- ALTERAÇÃO: Filtrar apenas contas com débito ---
        // Exibe apenas contas que devem mais que 1 centavo
        menuAccounts.value = calculatedAccounts.filter(acc => acc.total > 0.01);

        canTableBeVoided.value = parseFloat(res.data.total) <= 0.01;
      }
    } catch (e) {
      console.error("Erro ao carregar detalhes da sessão", e);
    } finally {
      isLoadingAccounts.value = false;
    }
  }
};

// Mantido para compatibilidade, embora o botão tenha sido removido do template
const onRequestNewAccount = () => {
  if (menuOp.value) menuOp.value.hide();
  emit("request-new-account", selectedTable.value);
};

const toggleTableStatus = async (activate) => {
  try {
    await api.post("/toggle-table-status", {
      id: selectedTable.value.id,
      active: activate,
    });
    notify(
      "success",
      "Sucesso",
      activate ? "Mesa reativada." : "Mesa desativada.",
    );
    store.fetchTables();
    if (menuOp.value) menuOp.value.hide();
  } catch (e) {
    const msg = e.response?.data?.message || "Falha ao alterar status.";
    notify("error", "Erro", msg);
  }
};

const handleLiberarMesa = async () => {
  if (
    !confirm(
      `Deseja LIBERAR a Mesa ${selectedTable.value.number} e encerrar a sessão?`,
    )
  )
    return;
  try {
    await api.post("/void-session", {
      session_id: selectedTable.value.sessionId,
    });
    notify("info", "Mesa Liberada", "A sessão foi encerrada com sucesso.");
    store.fetchTables();
    if (menuOp.value) menuOp.value.hide();
  } catch (e) {
    notify("error", "Erro", "Falha ao liberar mesa.");
  }
};

const quickAction = (action, payload = null) => {
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
    router.push("/staff/pdv");
  }
  else if (action === "payment_account") {
    sessionStore.setAccount(payload);
    router.push({ path: "/staff/pdv", query: { mode: "checkout" } });
  }

  if (menuOp.value) menuOp.value.hide();
};

onMounted(() => {
  store.fetchTables();
});
onActivated(() => {
  store.fetchTables();
});
</script>