<template>
  <div class="flex flex-col h-full w-full bg-surface-2 rounded-[24px] overflow-hidden border border-transparent">
    
    <div class="p-5 bg-surface-1 border-b border-surface-3/10 flex justify-between items-center shrink-0">
        <div>
            <span class="text-[10px] uppercase font-black text-surface-500 tracking-widest block mb-1">
                {{ mode === 'promise' ? 'Total a Pagar' : 'Valor a Receber' }}
            </span>
            <span class="text-2xl font-black text-surface-0">
                {{ formatCurrency(totalDue) }}
            </span>
        </div>
        <div class="text-right">
            <span class="text-[10px] uppercase font-black tracking-widest block mb-1"
                  :class="Math.abs(uiRemainingAmount) < 0.02 ? 'text-green-500' : 'text-red-400'">
                {{ Math.abs(uiRemainingAmount) < 0.02 ? 'Fechado' : 'Faltam' }}
            </span>
            <span class="text-lg font-bold" :class="Math.abs(uiRemainingAmount) < 0.02 ? 'text-green-400' : 'text-red-400'">
                {{ formatCurrency(uiRemainingAmount) }}
            </span>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto bg-surface-1 p-2 space-y-2 min-h-[100px] scrollbar-thin">
        
        <div v-if="payments.length === 0" class="flex flex-col items-center justify-center h-full text-surface-600 opacity-50 py-4">
            <i class="fa-solid fa-wallet text-3xl mb-2"></i>
            <p class="text-xs font-bold uppercase tracking-wider">
                {{ currentMethod ? `Pagamento em ${currentMethod.label}` : 'Selecione a forma' }}
            </p>
        </div>

        <transition-group name="list" tag="div" class="space-y-2">
            <div v-for="(pay, index) in payments" :key="index" 
                 class="bg-surface-1 p-3 rounded-[16px] border border-surface-3/10 hover:bg-surface-2 flex justify-between items-center group animate-fade-in relative overflow-hidden"
            >
                <div class="flex items-center gap-3 relative z-10">
                    <div class="w-9 h-9 rounded-full bg-surface-2 flex items-center justify-center text-primary-500 border border-surface-3/10">
                        <i :class="getMethodIcon(pay)"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-sm text-surface-0">{{ getMethodLabel(pay) }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-3 relative z-10">
                    <span class="font-black text-surface-0">{{ formatCurrency(pay.amount) }}</span>
                    <button @click="removePayment(index)" class="text-surface-500 hover:text-red-400 transition-colors w-7 h-7 flex items-center justify-center rounded-full hover:bg-surface-2">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
        </transition-group>
    </div>

    <div class="p-4 bg-surface-1 border-t border-surface-3/10 shrink-0 space-y-4">
        
        <div class="grid grid-cols-3 gap-2">
            <button 
                v-for="method in availableMethods" 
                :key="method.id"
                class="px-1 py-2 rounded-xl border-2 text-[10px] font-black uppercase transition-all flex flex-col items-center justify-center gap-1 h-16 active:scale-95"
                :class="currentMethod?.id === method.id 
                    ? 'bg-primary-500/10 border-primary-500 text-primary-400' 
                    : 'bg-surface-2 border-transparent text-surface-400 hover:text-surface-0 hover:bg-surface-3'"
                @click="selectMethod(method)"
            >
                <i :class="method.icon" class="text-lg mb-0.5"></i> 
                <span class="truncate w-full text-center px-1">{{ method.label }}</span>
            </button>
        </div>

        <div class="flex items-center gap-2 w-full flex-nowrap overflow-hidden">
            
            <div class="flex-1 min-w-0">
                <InputNumber 
                    v-model="amountInput" 
                    mode="currency" currency="BRL" locale="pt-BR"
                    class="w-full" 
                    placeholder="Valor"
                    :minFractionDigits="2"
                    :pt="{
                        root: { 
                            class: 'flex w-full',
                            style: { width: '100%' } 
                        }, 
                        input: { 
                            class: '!bg-surface-2 !border-none !text-surface-0 !rounded-full !text-center !font-black focus:!ring-2 focus:!ring-primary-500 !h-12 !p-0 !text-base sm:!text-lg',
                            style: { width: '100%', minWidth: '0' } 
                        } 
                    }"
                    @keydown.enter="manualAddPayment"
                />
            </div>
            
            <div v-if="isMoney(currentMethod)" class="flex-1 min-w-0 animate-slide-left relative flex flex-col justify-center">
                <label class="absolute -top-[7px] left-1/2 -translate-x-1/2 text-[8px] bg-surface-1 px-1 text-surface-400 font-black uppercase z-10 pointer-events-none whitespace-nowrap">
                    Troco p/
                </label>
                <InputNumber 
                    v-model="changeInput" 
                    mode="currency" currency="BRL" locale="pt-BR"
                    placeholder="S/ Troco"
                    class="w-full"
                    :minFractionDigits="2"
                    :pt="{
                        root: { 
                            class: 'flex w-full',
                            style: { width: '100%' } 
                        }, 
                        input: { 
                            class: '!bg-surface-2 !border-none !text-surface-0 !rounded-full !text-center !text-sm !font-bold focus:!ring-2 focus:!ring-primary-500 !h-12 !p-0 placeholder:!text-surface-600',
                            style: { width: '100%', minWidth: '0' } 
                        } 
                    }"
                    @keydown.enter="manualAddPayment"
                />
            </div>

            <Button 
                icon="fa-solid fa-plus" 
                class="!bg-primary-500 hover:!bg-primary-400 !text-surface-0 !border-none !w-12 !h-12 !rounded-full shrink-0 flex items-center justify-center !shadow-none hover:!shadow-lg transition-all" 
                @click="manualAddPayment" 
            />
        </div>
    </div>

    <div v-if="showFooter" class="p-4 pt-0 bg-surface-1 shrink-0 border-t border-transparent">
        <Button 
            :label="mainButtonLabel" 
            :icon="mode === 'promise' ? 'fa-solid fa-save' : 'fa-solid fa-check-double'" 
            class="w-full !font-black !h-14 !rounded-full transition-all shadow-lg text-lg uppercase tracking-wide"
            :class="isValid ? '!bg-green-600 hover:!bg-green-500 !border-none !text-surface-0' : '!bg-surface-3 !text-surface-500 cursor-not-allowed'"
            :disabled="!isValid"
            @click="handleSubmit"
        />
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import api from '@/services/api';
import { useFormat } from '@/composables/useFormat'; 
import { notify } from '@/services/notify';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';

const props = defineProps({
    totalDue: { type: Number, required: true },
    initialPayments: { type: Array, default: () => [] }, 
    mode: { type: String, default: 'promise' }, 
    showFooter: { type: Boolean, default: true }
});

const emit = defineEmits(['confirm', 'update:payments', 'validity-change']);
const { formatCurrency } = useFormat();

const payments = ref([]);
const availableMethods = ref([]);
const currentMethod = ref(null);

// Inputs
const amountInput = ref(0);
const changeInput = ref(null);

onMounted(async () => {
    await fetchMethods();
    if (props.initialPayments && props.initialPayments.length > 0) {
        payments.value = JSON.parse(JSON.stringify(props.initialPayments));
    } else {
        autoFillAmount();
    }
});

watch(() => props.totalDue, autoFillAmount);

// Watch para emitir atualizações para o pai
watch([payments, amountInput, currentMethod, changeInput], () => {
    const final = buildFinalPayments();
    emit('update:payments', final);
    
    // Validação considerando o implícito
    const totalCovered = final.reduce((acc, p) => acc + (parseFloat(p.amount) || 0), 0);
    const valid = Math.abs(props.totalDue - totalCovered) < 0.02;
    emit('validity-change', valid);
    
}, { deep: true });

async function fetchMethods() {
    try {
        const { data } = await api.get('/payment-methods?context=pos');
        if (data.success) {
            availableMethods.value = data.categories.flatMap(c => c.methods);
            const money = availableMethods.value.find(m => m.type === 'money');
            currentMethod.value = money || availableMethods.value[0];
        }
    } catch (e) { console.error(e); }
}

function autoFillAmount() {
    // Calcula quanto falta apenas com os pagamentos EXPLÍCITOS da lista
    const explicitTotal = payments.value.reduce((acc, p) => acc + (parseFloat(p.amount) || 0), 0);
    const diff = Math.max(0, props.totalDue - explicitTotal);
    
    if (diff > 0) {
        amountInput.value = diff;
    } else {
        amountInput.value = 0;
    }
    changeInput.value = null;
}

// Total Apenas da Lista (Visual)
const explicitTotal = computed(() => {
    return payments.value.reduce((acc, p) => acc + (parseFloat(p.amount) || 0), 0);
});

// Constrói o array final combinando lista + input atual (implícito)
function buildFinalPayments() {
    const list = [...payments.value];
    const inputVal = parseFloat(amountInput.value || 0);
    
    // Adiciona o pagamento implícito se houver método, valor > 0, e não houver pagamentos suficientes
    if (currentMethod.value && inputVal > 0) {
        let finalChange = 0;
        let finalAmount = inputVal;

        // Lógica de Troco no Implícito
        if (isMoney(currentMethod.value)) {
            if (changeInput.value) {
                finalChange = parseFloat(changeInput.value);
            } else if (inputVal > (props.totalDue - explicitTotal.value + 0.01)) {
                // Se digitou mais que o restante sem usar campo de troco, assume que é para troco
                finalChange = inputVal;
                finalAmount = Math.max(0, props.totalDue - explicitTotal.value);
            }
        } else {
            // Se não é dinheiro, limita ao restante para evitar overpayment implícito
            const remaining = Math.max(0, props.totalDue - explicitTotal.value);
            if (finalAmount > remaining) finalAmount = remaining;
        }

        if (finalAmount > 0) {
            list.push({
                method: currentMethod.value.type,
                method_id: currentMethod.value.id,
                amount: finalAmount,
                label: currentMethod.value.label,
                change_for: finalChange
            });
        }
    }
    return list;
}

// Restante Visual (Considerando o Implícito para feedback imediato)
const uiRemainingAmount = computed(() => {
    const final = buildFinalPayments();
    const total = final.reduce((acc, p) => acc + (parseFloat(p.amount) || 0), 0);
    return props.totalDue - total;
});

const isValid = computed(() => {
    return Math.abs(uiRemainingAmount.value) < 0.02;
});

const mainButtonLabel = computed(() => {
    if (props.mode === 'promise') return 'Confirmar Pagamento';
    return 'Confirmar Baixa (F10)';
});

const isMoney = (method) => {
    if (!method) return false;
    return (method.type === 'money' || method.method === 'money');
};

const getMethodLabel = (pay) => {
    if (pay.method_id) {
        const m = availableMethods.value.find(x => x.id == pay.method_id);
        if (m) return m.label;
    }
    const m = availableMethods.value.find(x => x.type === pay.method);
    return m ? m.label : (pay.label || pay.method);
};

const getMethodIcon = (pay) => {
    if (pay.method_id) {
        const m = availableMethods.value.find(x => x.id == pay.method_id);
        if (m) return m.icon;
    }
    const m = availableMethods.value.find(x => x.type === pay.method);
    return m ? m.icon : 'fa-solid fa-circle';
};

const selectMethod = (method) => {
    currentMethod.value = method;
    if (method.type !== 'money') changeInput.value = null;
    autoFillAmount();
};

const manualAddPayment = () => {
    // Adiciona explicitamente à lista (botão +)
    let val = parseFloat(amountInput.value || 0);
    const explicitRemaining = props.totalDue - explicitTotal.value;

    if (val <= 0 || !currentMethod.value) return;

    let finalChange = 0;
    
    if (isMoney(currentMethod.value)) {
        if (val > (explicitRemaining + 0.01) && !changeInput.value) {
             finalChange = val; 
             val = explicitRemaining; 
        } else {
             finalChange = parseFloat(changeInput.value || 0);
        }

        if (finalChange > 0) {
            const changeValue = finalChange - val;
            if (changeValue < 0) { notify('warn', 'Atenção', 'Valor para troco menor que o valor a pagar.'); return; }
            if (changeValue > 50) { notify('warn', 'Limite de Troco', `Máximo permitido: R$ 50,00.`); return; }
        }
    } else {
        if (val > (explicitRemaining + 0.01)) val = explicitRemaining;
    }

    payments.value.push({
        method: currentMethod.value.type,
        method_id: currentMethod.value.id,
        amount: val,
        label: currentMethod.value.label,
        change_for: finalChange
    });

    changeInput.value = null;
    // autoFillAmount disparado pelo watcher
};

const removePayment = (index) => {
    payments.value.splice(index, 1);
};

const handleSubmit = () => {
    if (isValid.value) {
        emit('confirm', buildFinalPayments());
    }
};
</script>

<style scoped>
.animate-fade-in { animation: fadeIn 0.3s ease-out; }
.animate-slide-left { animation: slideLeft 0.3s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
@keyframes slideLeft { from { opacity: 0; transform: translateX(10px); } to { opacity: 1; transform: translateX(0); } }

.list-enter-active, .list-leave-active { transition: all 0.3s ease; }
.list-enter-from, .list-leave-to { opacity: 0; transform: translateX(-20px); }

:deep(.p-inputnumber) {
    display: flex !important;
    width: 100% !important;
    flex: 1 1 0% !important;
    min-width: 0 !important;
}

:deep(.p-inputnumber-input) {
    width: 100% !important;
    flex: 1 1 0% !important;
    min-width: 0 !important;
    max-width: 100% !important; 
}

.flex-nowrap {
    display: flex !important;
    flex-wrap: nowrap !important;
}
</style>