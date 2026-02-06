<template>
    <Dialog 
        v-model:visible="isVisible" 
        modal 
        :position="isMobile ? 'bottom' : 'center'"
        :dismissableMask="false"
        :closable="false"
        :draggable="false"
        :showHeader="false"
        :style="{ width: '100%', maxWidth: '450px' }"
        :pt="ptOptions"
    >
        <div class="flex flex-col h-full relative overflow-hidden">
            
            <div class="w-full flex justify-center pt-3 pb-1 shrink-0">
                <div class="w-12 h-1.5 bg-surface-3/50 rounded-full"></div>
            </div>

            <div class="px-8 pt-4 pb-2 text-center shrink-0">
                <div class="w-16 h-16 rounded-full bg-primary-500/10 flex items-center justify-center mx-auto mb-4 text-primary-500 shadow-[0_0_20px_rgba(var(--primary-500-rgb),0.2)]">
                    <i class="fa-solid fa-user-tag text-2xl"></i>
                </div>
                <h2 class="text-2xl font-black text-surface-0 mb-1 leading-tight">Quase lá!</h2>
                <p class="text-sm text-surface-400 font-medium leading-relaxed">
                    Confirme seus dados para continuar.
                </p>
            </div>

            <div class="p-8 pt-6 flex-1">
                
                <transition name="slide-fade" mode="out-in">
                    
                    <div v-if="step === 1" key="step-1" class="flex flex-col gap-6">
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-black text-surface-400 uppercase tracking-widest ml-1">Seu CPF</label>
                            <div class="relative group">
                                <i class="fa-solid fa-id-card absolute left-4 top-1/2 -translate-y-1/2 text-surface-400 group-focus-within:text-primary-500 transition-colors z-10 pointer-events-none"></i>
                                <InputMask 
                                    v-model="form.cpf" 
                                    mask="999.999.999-99" 
                                    placeholder="000.000.000-00" 
                                    class="w-full !pl-11 !h-14 !rounded-2xl !bg-surface-2 !border-none !text-surface-0 !text-lg !font-bold focus:!ring-2 focus:!ring-primary-500/50 shadow-inner" 
                                    autoFocus
                                    @keydown.enter="validateCpf"
                                />
                            </div>
                            <small class="text-red-400 text-xs font-bold pl-1 h-4 block">{{ errors.cpf }}</small>
                        </div>

                        <Button 
                            label="CONTINUAR" 
                            icon="fa-solid fa-arrow-right" 
                            iconPos="right"
                            class="!w-full !h-14 !rounded-full !bg-primary-500 hover:!bg-primary-400 !border-none !text-surface-950 !font-black !text-base shadow-lg shadow-primary-900/20 active:scale-[0.98] transition-all mt-2" 
                            :loading="loading"
                            @click="validateCpf" 
                        />
                    </div>

                    <div v-else key="step-2" class="flex flex-col gap-5">
                        
                        <div class="bg-surface-2/50 border border-surface-3/30 rounded-[20px] p-4 flex flex-col gap-4 relative overflow-hidden">
                            <div class="absolute top-0 right-0 px-3 py-1 bg-green-500/20 text-green-400 text-[9px] font-black uppercase rounded-bl-xl border-l border-b border-green-500/20">
                                <i class="fa-solid fa-check-circle mr-1"></i> Validado
                            </div>

                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-full bg-surface-3 flex items-center justify-center text-surface-400 shrink-0 mt-0.5">
                                    <i class="fa-solid fa-user text-sm"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-black text-surface-500 uppercase tracking-widest">Nome Civil</span>
                                    <span class="text-sm font-black text-surface-100 leading-tight">{{ form.name }}</span>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-full bg-surface-3 flex items-center justify-center text-surface-400 shrink-0 mt-0.5">
                                    <i class="fa-solid fa-cake-candles text-sm"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-black text-surface-500 uppercase tracking-widest">Nascimento</span>
                                    <span class="text-sm font-black text-surface-100">{{ form.dob }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-black text-surface-400 uppercase tracking-widest ml-1">Celular / WhatsApp</label>
                            <div class="relative group">
                                <i class="fa-brands fa-whatsapp absolute left-4 top-1/2 -translate-y-1/2 text-surface-400 group-focus-within:text-green-500 transition-colors z-10 pointer-events-none"></i>
                                <InputMask 
                                    v-model="form.phone" 
                                    mask="(99) 99999-9999" 
                                    placeholder="(00) 00000-0000" 
                                    class="w-full !pl-11 !h-14 !rounded-2xl !bg-surface-2 !border-none !text-surface-0 !font-bold focus:!ring-2 focus:!ring-green-500/50 shadow-inner" 
                                />
                            </div>
                        </div>

                        <Button 
                            label="CONFIRMAR E FINALIZAR" 
                            icon="fa-solid fa-check" 
                            class="!w-full !h-14 !rounded-full !bg-green-500 hover:!bg-green-400 !border-none !text-surface-950 !font-black !text-base shadow-lg shadow-green-900/20 active:scale-[0.98] transition-all mt-2" 
                            :loading="loading"
                            @click="submitAll" 
                        />
                        
                        <button @click="step = 1" class="text-xs font-bold text-surface-500 hover:text-surface-300 transition-colors text-center w-full py-2">
                            Não sou eu (Corrigir CPF)
                        </button>
                    </div>

                </transition>
            </div>
        </div>
    </Dialog>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useUserStore } from '@/stores/user-store';
import { useMobile } from '@/composables/useMobile';
import { isValidCPF, cleanDigits } from '@/utils/validators';
import api from '@/services/api';
import { notify } from '@/services/notify';

// Components
import Dialog from 'primevue/dialog';
import InputMask from 'primevue/inputmask';
import Button from 'primevue/button';

const props = defineProps(['visible']);
const emit = defineEmits(['update:visible', 'completed']);

const userStore = useUserStore();
const { isMobile } = useMobile();

const isVisible = computed({
    get: () => props.visible,
    set: (val) => emit('update:visible', val)
});

const step = ref(1);
const loading = ref(false);
const errors = reactive({ cpf: '' });

const form = reactive({
    cpf: '',
    name: '',
    phone: '',
    dob: ''
});

// Estilo "Material You / iOS Sheet"
const ptOptions = computed(() => {
    const common = {
        mask: { class: '!bg-surface-base/90 backdrop-blur-md' },
        content: { class: '!p-0 !bg-transparent !h-full' }
    };

    if (isMobile.value) {
        return {
            ...common,
            root: { class: '!bg-surface-1 !border-none !rounded-t-[32px] !shadow-[0_-10px_40px_rgba(0,0,0,0.6)] !w-full !m-0 !max-h-[85vh]' }
        };
    } else {
        return {
            ...common,
            root: { class: '!bg-surface-1 !border-none !rounded-[32px] !shadow-2xl' }
        };
    }
});

const validateCpf = async () => {
    errors.cpf = '';
    const clean = cleanDigits(form.cpf);

    if (!isValidCPF(clean)) {
        errors.cpf = 'CPF inválido.';
        return;
    }

    loading.value = true;
    try {
        // Valida na API (Gov.br + Duplicidade)
        const { data } = await api.post('/onboarding/validate-cpf', { cpf: clean });
        
        if (data.success) {
            // Preenche dados retornados pela API (Gov.br)
            if (data.data?.name) form.name = data.data.name;
            
            if (data.data?.dob) {
                // Formata YYYY-MM-DD para DD/MM/YYYY para exibição
                const [y, m, d] = data.data.dob.split('-');
                form.dob = `${d}/${m}/${y}`;
            }
            
            // Avança para o passo de confirmação
            step.value = 2;
        } else {
            if (data.code === 'cpf_exists') {
                errors.cpf = data.hint || 'Este CPF já está em uso.';
                notify('warn', 'Atenção', 'CPF já cadastrado em outra conta.');
            } else {
                errors.cpf = data.message || 'Erro ao validar.';
            }
        }
    } catch (e) {
        errors.cpf = 'Erro de conexão. Tente novamente.';
        console.error(e);
    } finally {
        loading.value = false;
    }
};

const submitAll = async () => {
    if (cleanDigits(form.phone).length < 10) {
        notify('warn', 'Atenção', 'Informe um telefone válido.');
        return;
    }

    loading.value = true;
    try {
        // Converte DD/MM/YYYY de volta para YYYY-MM-DD para salvar no banco
        const [d, m, y] = form.dob.split('/');
        const isoDob = (d && m && y) ? `${y}-${m}-${d}` : '';

        const payload = {
            cpf: cleanDigits(form.cpf),
            name: form.name,
            phone: cleanDigits(form.phone),
            dob: isoDob
        };

        const { data } = await api.post('/onboarding/complete', payload);

        if (data.success) {
            // Atualiza store localmente
            userStore.user.onboarding_complete = true;
            userStore.user.name = form.name; 
            
            emit('completed');
            isVisible.value = false;
        }
    } catch (e) {
        notify('error', 'Erro', e.response?.data?.message || 'Falha ao salvar dados.');
    } finally {
        loading.value = false;
    }
};
</script>

<style scoped>
.slide-fade-enter-active,
.slide-fade-leave-active {
  transition: all 0.3s ease;
}

.slide-fade-enter-from {
  opacity: 0;
  transform: translateX(10px);
}

.slide-fade-leave-to {
  opacity: 0;
  transform: translateX(-10px);
}
</style>