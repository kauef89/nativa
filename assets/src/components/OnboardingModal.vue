<template>
  <Dialog 
    v-model:visible="visible" 
    modal 
    position="bottom" 
    :dismissableMask="false"
    :closable="false"
    :showHeader="false"
    class="!w-full !m-0 !max-w-md !mx-auto !bg-transparent !shadow-none"
    :contentStyle="{ padding: '0', background: 'transparent' }"
    :pt="{
        mask: { class: '!bg-surface-950/80 backdrop-blur-sm' },
        root: { class: '!border-0 !rounded-none' }
    }"
  >
    <div class="bg-surface-900/60 backdrop-blur-xl border-t border-white/10 rounded-t-[2.5rem] p-8 pb-12 shadow-[0_-10px_40px_rgba(0,0,0,0.6)] relative overflow-hidden animate-slide-up">
        
        <div class="absolute top-0 right-0 w-48 h-48 bg-primary-500/20 rounded-full blur-[60px] pointer-events-none -mr-10 -mt-10"></div>
        <div class="w-16 h-1.5 bg-surface-700/50 rounded-full mx-auto mb-8"></div>

        <div class="relative z-10 text-center mb-8 transition-all duration-300">
            <h2 class="text-2xl font-black text-white mb-2 tracking-tight">
                {{ step === 3 ? 'Quase lá!' : 'Boas-vindas! 👋' }}
            </h2>
            <p class="text-surface-300 text-sm leading-relaxed">
                {{ step === 3 ? 'Informe seu WhatsApp para receber o pedido.' : 'Vamos confirmar seus dados para prosseguir.' }}
            </p>
        </div>

        <div class="relative z-10 min-h-[200px] flex flex-col gap-4">
            
            <transition name="fade-height">
                <div v-if="step < 3" class="flex flex-col gap-2">
                    <label class="text-xs font-bold text-primary-400 uppercase tracking-wider ml-1">Informe seu CPF</label>
                    <div class="relative group">
                        <i class="fa-solid fa-id-card absolute left-4 top-1/2 -translate-y-1/2 text-surface-400 transition-colors" :class="{'text-primary-500': step === 2}"></i>
                        <InputMask 
                            id="input-cpf"
                            v-model="form.cpf" 
                            mask="999.999.999-99" 
                            placeholder="000.000.000-00" 
                            class="glass-input !pl-12"
                            :class="{'!bg-surface-800/50 !border-transparent !text-surface-400': step === 2}" 
                            :disabled="step === 2"
                            @keydown.enter="handleMainAction"
                        />
                        <i v-if="step === 2" class="fa-solid fa-circle-check absolute right-4 top-1/2 -translate-y-1/2 text-green-500 text-xl animate-pop-in"></i>
                    </div>
                </div>
            </transition>

            <transition name="slide-fade">
                <div v-if="step >= 2" class="bg-surface-800/40 border border-white/5 rounded-2xl p-5 relative overflow-hidden group">
                    <div class="absolute inset-0 bg-gradient-to-br from-primary-500/5 to-transparent opacity-50"></div>
                    
                    <div class="flex items-start gap-4 relative z-10">
                        <div class="w-12 h-12 rounded-full bg-surface-800 flex items-center justify-center text-primary-400 text-xl border border-surface-700 shadow-inner shrink-0">
                            <i class="fa-solid fa-user-check"></i>
                        </div>
                        <div class="flex flex-col items-start">
                            <div class="text-[10px] text-surface-400 uppercase font-bold tracking-wider mb-0.5">Identificamos você</div>
                            <div class="text-lg font-bold text-white leading-tight capitalize mb-2">{{ govData.name.toLowerCase() }}</div>
                            <div class="inline-flex items-center gap-2 bg-surface-950/50 border border-surface-700/50 rounded-full px-3 py-1 text-xs text-surface-300">
                                <i class="fa-solid fa-cake-candles text-pink-400"></i>
                                <span class="font-mono font-bold">{{ formatDate(govData.dob) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>

            <transition name="slide-fade">
                <div v-if="step === 3" class="flex flex-col gap-2 mt-2">
                    <label class="text-xs font-bold text-primary-400 uppercase tracking-wider ml-1">Seu WhatsApp</label>
                    <div class="relative group">
                        <i class="fa-brands fa-whatsapp absolute left-4 top-1/2 -translate-y-1/2 text-surface-400 group-focus-within:text-green-400 transition-colors"></i>
                        <InputMask 
                            id="input-whatsapp"
                            v-model="form.phone" 
                            mask="(99) 99999-9999" 
                            placeholder="(00) 00000-0000" 
                            class="glass-input !pl-12" 
                            autoFocus
                            @keydown.enter="handleMainAction"
                        />
                    </div>
                    <p class="text-[10px] text-surface-500 text-center">Enviaremos o status do seu pedido por aqui.</p>
                </div>
            </transition>

            <div v-if="errorMsg" class="bg-red-500/10 border border-red-500/20 p-4 rounded-2xl flex items-start gap-3 animate-shake mt-2">
                <i class="fa-solid fa-circle-exclamation text-red-400 mt-0.5 text-lg"></i>
                <div class="text-sm text-red-200 leading-snug">
                    <span class="font-bold block mb-1 text-red-100">Atenção</span>
                    {{ errorMsg }}
                </div>
            </div>

            <div class="mt-4">
                <Button 
                    :label="buttonLabel" 
                    :icon="buttonIcon" 
                    iconPos="right" 
                    class="glass-btn" 
                    :class="{'!bg-primary-500 hover:!bg-primary-400': step === 3}"
                    :loading="loading" 
                    @click="handleMainAction"
                />
                
                <Button 
                    v-if="step > 1" 
                    label="Não sou eu / Voltar" 
                    text 
                    class="w-full mt-2 !text-surface-500 hover:!text-white !text-xs !uppercase !tracking-widest" 
                    @click="resetFlow" 
                />
            </div>

        </div>
    </div>
  </Dialog>
</template>

<script setup>
import { ref, reactive, watch, computed, nextTick } from 'vue';
import { useUserStore } from '@/stores/user-store';
import api from '@/services/api';
import InputMask from 'primevue/inputmask';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';

const props = defineProps(['visible']);
const emit = defineEmits(['update:visible', 'completed']);

const userStore = useUserStore();
const visible = ref(props.visible);

// Foca no input ao abrir
watch(() => props.visible, (val) => {
    visible.value = val;
    if (val) {
        setTimeout(() => {
            const input = document.getElementById('input-cpf');
            if(input) input.focus();
        }, 300);
    }
});
watch(visible, (val) => emit('update:visible', val));

// Estados
const step = ref(1); // 1: Validação, 2: Confirmação, 3: WhatsApp
const loading = ref(false);
const errorMsg = ref('');
const form = reactive({ cpf: '', phone: '' });
const govData = ref({});

// Computed para Botão Dinâmico
const buttonLabel = computed(() => {
    if (step.value === 1) return 'Validar CPF';
    if (step.value === 2) return 'Confirmar';
    return 'Finalizar Cadastro';
});

const buttonIcon = computed(() => {
    if (step.value === 1) return 'fa-solid fa-magnifying-glass';
    if (step.value === 2) return 'fa-solid fa-check';
    return 'fa-solid fa-arrow-right';
});

// Ação Principal (Router do Botão)
const handleMainAction = () => {
    if (step.value === 1) validateCpf();
    else if (step.value === 2) confirmIdentity();
    else if (step.value === 3) finalize();
};

// Passo 1: Validar CPF
const validateCpf = async () => {
    errorMsg.value = '';
    const cleanCpf = form.cpf.replace(/\D/g, '');
    
    if (cleanCpf.length !== 11) { errorMsg.value = 'CPF incompleto.'; return; }
    if (/^(\d)\1+$/.test(cleanCpf)) { errorMsg.value = 'CPF inválido.'; return; }

    loading.value = true;
    try {
        const { data } = await api.post('/onboarding/validate-cpf', { cpf: cleanCpf });
        if (data.success) {
            govData.value = data.data;
            step.value = 2; // Vai para Confirmação
        } else {
            handleApiError(data);
        }
    } catch (e) {
        handleApiCatch(e);
    } finally {
        loading.value = false;
    }
};

// Passo 2: Confirmar Identidade
const confirmIdentity = () => {
    step.value = 3; // Vai para WhatsApp
    // Foca no novo input após a transição
    nextTick(() => {
        setTimeout(() => {
            const el = document.getElementById('input-whatsapp');
            if(el) el.focus();
        }, 300);
    });
};

// Passo 3: Finalizar
const finalize = async () => {
    const cleanPhone = form.phone.replace(/\D/g, '');
    if (cleanPhone.length < 10) return; 

    loading.value = true;
    try {
        const payload = {
            cpf: form.cpf.replace(/\D/g, ''),
            phone: cleanPhone,
            name: govData.value.name,
            dob: govData.value.dob
        };
        const { data } = await api.post('/onboarding/complete', payload);
            if (data.success) {
            // CORREÇÃO AQUI: Atualiza a Store de forma persistente
            const updatedUser = { 
                ...userStore.user, 
                name: payload.name, 
                onboarding_complete: true 
            };
            
            // Se você usou o código da Store acima, chame:
            if (userStore.setUser) {
                userStore.setUser(updatedUser);
            } else {
                // Fallback manual se não atualizou a store ainda
                userStore.user = updatedUser;
                localStorage.setItem("nativa_user", JSON.stringify(updatedUser));
            }

            emit('completed');
            visible.value = false;
        }
    } catch (e) {
        handleApiCatch(e);
    } finally {
        loading.value = false;
    }
};

// Resetar fluxo se usuário clicar em "Não sou eu"
const resetFlow = () => {
    step.value = 1;
    form.cpf = '';
    form.phone = '';
    govData.value = {};
    errorMsg.value = '';
    nextTick(() => document.getElementById('input-cpf')?.focus());
};

// Helpers
const formatDate = (dateString) => {
    if (!dateString) return '';
    const parts = dateString.split('-'); 
    if (parts.length !== 3) return dateString;
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
};

const handleApiError = (data) => {
    if (data.code === 'cpf_exists') {
        errorMsg.value = `${data.message} ${data.hint}`;
    } else {
        errorMsg.value = data.message || 'Erro ao validar.';
    }
};

const handleApiCatch = (e) => {
    if (e.response?.data?.message) errorMsg.value = e.response.data.message;
    else errorMsg.value = 'Erro de conexão. Tente novamente.';
};
</script>

<style scoped>
.glass-input {
    @apply w-full h-14 bg-surface-950/50 border border-surface-700 rounded-xl px-4 text-white text-lg font-mono focus:border-primary-500 focus:bg-surface-900 focus:ring-1 focus:ring-primary-500/50 transition-all outline-none shadow-inner placeholder:text-surface-600;
}

.glass-btn {
    @apply !w-full !h-14 !rounded-xl !bg-white !text-surface-950 !font-black !text-lg !uppercase !tracking-wide shadow-[0_0_20px_rgba(255,255,255,0.2)] hover:!bg-surface-200 transition-all active:scale-[0.98] !border-none;
}

/* Animations */
.animate-slide-up { animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }

.animate-pop-in { animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
@keyframes popIn { 0% { transform: translateY(-50%) scale(0); } 100% { transform: translateY(-50%) scale(1); } }

.animate-shake { animation: shake 0.4s ease-in-out; }
@keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }

/* Vue Transitions */
.slide-fade-enter-active { transition: all 0.4s ease-out; }
.slide-fade-leave-active { transition: all 0.2s cubic-bezier(1, 0.5, 0.8, 1); }
.slide-fade-enter-from { transform: translateY(20px); opacity: 0; }
.slide-fade-leave-to { transform: translateY(-20px); opacity: 0; }

.fade-height-enter-active, .fade-height-leave-active { transition: all 0.3s ease; max-height: 100px; opacity: 1; }
.fade-height-enter-from, .fade-height-leave-to { max-height: 0; opacity: 0; margin: 0; overflow: hidden; }
</style>