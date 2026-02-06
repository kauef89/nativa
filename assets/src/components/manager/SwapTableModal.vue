<template>
  <component 
    :is="componentType"
    :visible="visible" 
    @update:visible="$emit('update:visible', $event)"
    :modal="true"
    :position="isMobile ? 'bottom' : 'center'"
    :dismissableMask="true"
    :closable="false"
    :style="modalStyle"
    class="bg-surface-1 transition-all duration-300"
    :pt="ptOptions"
  >
    <div class="shrink-0 z-20 relative bg-surface-1 pb-2">
        <div v-if="isMobile" class="w-full flex justify-center pt-3 pb-1 cursor-grab active:cursor-grabbing" @click="$emit('update:visible', false)">
            <div class="w-12 h-1.5 bg-surface-3/50 rounded-full"></div>
        </div>

        <div class="flex items-center justify-between px-6 pt-5 pb-2">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-primary-500/10 flex items-center justify-center text-primary-500 text-lg border border-primary-500/20">
                    <i class="fa-solid fa-arrow-right-arrow-left"></i>
                </div>
                <div class="flex flex-col">
                    <h2 class="text-xl font-black text-surface-0 leading-none">Trocar Mesa</h2>
                    <p class="text-xs font-bold text-surface-400 mt-1">Mover pedidos e contas</p>
                </div>
            </div>
            
            <button 
                class="w-10 h-10 rounded-full flex items-center justify-center transition-all bg-surface-2 text-surface-400 hover:text-surface-0 hover:bg-surface-3"
                @click="$emit('update:visible', false)"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-6 bg-surface-1 scrollbar-thin flex flex-col gap-6">
        
        <div class="bg-surface-2 rounded-[24px] p-1 border border-surface-3/10 relative overflow-hidden shrink-0">
            <div class="flex items-center justify-between relative z-10">
                <div class="flex-1 flex flex-col items-center gap-1 p-3 bg-surface-2 rounded-[20px]">
                    <span class="text-[9px] font-black uppercase text-surface-500 tracking-widest">De</span>
                    <span class="text-2xl font-black text-surface-0 leading-none">{{ sourceTable?.number || '?' }}</span>
                </div>

                <div class="text-surface-400 opacity-50"><i class="fa-solid fa-chevron-right"></i></div>

                <div class="flex-1 flex flex-col items-center gap-1 p-3 bg-surface-2 rounded-[20px] transition-colors"
                     :class="targetTable ? 'bg-primary-500/10' : ''">
                    <span class="text-[9px] font-black uppercase text-surface-500 tracking-widest">Para</span>
                    <span class="text-2xl font-black leading-none" :class="targetTable ? 'text-primary-500' : 'text-surface-600'">
                        {{ targetTable ? targetTable.number : '?' }}
                    </span>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <label class="text-[10px] uppercase font-black text-surface-500 tracking-widest pl-1">Mesa Destino</label>
            
            <div class="bg-surface-2/50 rounded-[20px] p-4 border border-surface-3/10">
                <div v-if="availableTables.length === 0" class="text-center py-4 text-surface-500 text-xs">
                    Nenhuma mesa livre disponível.
                </div>
                
                <div v-else class="grid grid-cols-5 gap-3">
                    <button 
                        v-for="table in availableTables" 
                        :key="table.id"
                        class="aspect-square rounded-2xl flex items-center justify-center font-black text-sm transition-all duration-200 border-2"
                        :class="targetTable?.id === table.id 
                            ? 'bg-primary-500 border-primary-500 text-surface-950 shadow-lg scale-110 z-10' 
                            : 'bg-surface-1 border-surface-3/10 text-surface-400 hover:border-surface-400 hover:text-surface-0'"
                        @click="targetTable = table"
                    >
                        {{ table.number }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="accounts && accounts.length > 0" class="flex flex-col gap-2">
            <div class="flex justify-between items-end pl-1">
                <label class="text-[10px] uppercase font-black text-surface-500 tracking-widest">Mover Contas</label>
                <button 
                    class="text-[10px] font-bold text-primary-400 hover:text-primary-300"
                    @click="toggleAllAccounts"
                >
                    {{ selectedAccounts.length === accounts.length ? 'Desmarcar Todas' : 'Selecionar Todas' }}
                </button>
            </div>

            <div class="flex flex-wrap gap-2">
                <button 
                    v-for="acc in accounts" 
                    :key="acc"
                    class="px-4 py-2.5 rounded-full text-xs font-bold transition-all border flex items-center gap-2"
                    :class="selectedAccounts.includes(acc)
                        ? 'bg-surface-0 text-surface-950 border-surface-0 shadow-md'
                        : 'bg-surface-2 text-surface-400 border-transparent hover:bg-surface-3 hover:text-surface-200'"
                    @click="toggleAccount(acc)"
                >
                    <i v-if="selectedAccounts.includes(acc)" class="fa-solid fa-check text-[10px]"></i>
                    {{ acc }}
                </button>
            </div>
            
            <p class="text-[10px] text-surface-500 pl-2 mt-1">
                * Se nenhuma for selecionada, movemos a <strong>Mesa Inteira</strong>.
            </p>
        </div>

    </div>

    <template #footer>
        <div class="p-6 pt-4 bg-surface-1 border-t border-surface-3/10">
            <Button 
                :label="getButtonLabel" 
                icon="fa-solid fa-check" 
                class="!w-full !h-14 !bg-primary-600 hover:!bg-primary-500 !border-none !text-surface-950 !font-black !rounded-full !text-sm !uppercase !tracking-widest shadow-xl shadow-primary-900/20 active:scale-[0.98] transition-all"
                :disabled="!targetTable || isSubmitting"
                :loading="isSubmitting"
                @click="confirmSwap"
            />
        </div>
    </template>
  </component>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useTablesStore } from '@/stores/tables-store';
import { useMobile } from '@/composables/useMobile';
import api from '@/services/api';
import { notify } from '@/services/notify';

// UI Components
import Dialog from 'primevue/dialog';
import Drawer from 'primevue/drawer';
import Button from 'primevue/button';

const props = defineProps({
    visible: Boolean,
    sourceTable: Object,
    accounts: { type: Array, default: () => [] }
});

const emit = defineEmits(['update:visible', 'success']);

const { isMobile } = useMobile();
const store = useTablesStore();

const targetTable = ref(null);
const selectedAccounts = ref([]);
const isSubmitting = ref(false);

// --- Componente Híbrido ---
const componentType = computed(() => isMobile.value ? Drawer : Dialog);

const modalStyle = computed(() => isMobile.value 
    ? { width: '100%', height: 'auto', maxHeight: '90vh' } 
    : { width: '500px', maxHeight: '90vh' }
);

const ptOptions = computed(() => {
    const common = {
        header: { class: '!hidden' }, 
        content: { class: '!bg-surface-1 !p-0 !overflow-hidden !flex !flex-col !rounded-t-[32px] md:!rounded-[28px]' },
        footer: { class: '!bg-surface-1 !border-none !p-0' }
    };

    if (isMobile.value) {
        return {
            ...common,
            root: { class: '!bg-surface-1 !border-none !rounded-t-[32px] !shadow-2xl !overflow-hidden' },
            mask: { class: '!bg-surface-base/80 backdrop-blur-md' }
        };
    } else {
        return {
            ...common,
            root: { class: '!bg-surface-1 !border-none !rounded-[28px] !overflow-hidden !shadow-2xl' },
            mask: { class: '!bg-surface-base/60 backdrop-blur-sm' }
        };
    }
});

// --- Lógica ---

const availableTables = computed(() => {
    // Filtra mesas livres e ordena numericamente
    return store.tables
        .filter(t => t.is_active && t.id !== props.sourceTable?.id && t.status !== 'occupied')
        .sort((a, b) => {
            const numA = parseInt(a.number) || 0;
            const numB = parseInt(b.number) || 0;
            return numA - numB;
        });
});

const getButtonLabel = computed(() => {
    if (!targetTable.value) return 'Selecione a Mesa';
    if (selectedAccounts.value.length === 0 || selectedAccounts.value.length === props.accounts.length) return 'Mover Tudo';
    return `Mover ${selectedAccounts.value.length} conta(s)`;
});

// Reset
watch(() => props.visible, (val) => {
    if (val) {
        targetTable.value = null;
        selectedAccounts.value = [];
        if (store.tables.length === 0) store.fetchTables();
    }
});

const toggleAccount = (acc) => {
    const idx = selectedAccounts.value.indexOf(acc);
    if (idx > -1) selectedAccounts.value.splice(idx, 1);
    else selectedAccounts.value.push(acc);
};

const toggleAllAccounts = () => {
    if (selectedAccounts.value.length === props.accounts.length) {
        selectedAccounts.value = [];
    } else {
        selectedAccounts.value = [...props.accounts];
    }
};

const confirmSwap = async () => {
    if (!targetTable.value) return;
    
    isSubmitting.value = true;
    try {
        const payload = {
            from_number: props.sourceTable.number,
            to_number: targetTable.value.number,
            // Se nenhuma selecionada = assume todas (backend logic ou frontend logic). 
            // Geralmente se array vazio, backend move mesa toda. Se array preenchido, move apenas contas.
            accounts: selectedAccounts.value.length > 0 ? selectedAccounts.value : null
        };

        const { data } = await api.post('/swap-table', payload);
        
        if (data.success) {
            notify('success', 'Sucesso', 'Troca realizada com sucesso!');
            emit('success');
            emit('update:visible', false);
        } else {
            notify('warn', 'Atenção', data.message || 'Não foi possível trocar.');
        }
    } catch (e) {
        notify('error', 'Erro', e.response?.data?.message || 'Falha na comunicação.');
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<style scoped>
.animate-fade-in { animation: fadeIn 0.3s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>