<template>
  <div class="flex items-center justify-center min-h-full p-4 bg-surface-950">
    
    <div class="w-full max-w-md bg-surface-900 rounded-2xl border border-surface-800 shadow-2xl overflow-hidden animate-fade-in">
        
        <div class="bg-surface-800 p-6 text-center border-b border-surface-700 relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary-500 to-purple-500"></div>
            
            <img src="../../svg/nativa_logo.svg" class="h-10 mx-auto mb-3" alt="Nativa" />
            
            <h2 class="text-xl font-bold text-white">
                {{ userStore.isAuthenticated ? 'Confirme seus dados' : 'Bem-vindo(a)!' }}
            </h2>
            <p class="text-surface-400 text-sm mt-1">
                {{ userStore.isAuthenticated ? 'Para emitirmos sua nota fiscal.' : 'Identifique-se para começar.' }}
            </p>
        </div>

        <div class="p-8">
            
            <div v-if="!userStore.isAuthenticated" class="flex flex-col gap-4 animate-fade-in-up">
                
                <p class="text-center text-surface-300 text-sm mb-2">Escolha como deseja entrar:</p>

                <button 
                    class="flex items-center justify-center w-full h-14 bg-white text-surface-900 font-bold rounded-xl hover:bg-gray-100 transition-all shadow-lg active:scale-95"
                    @click="loginWithGoogle"
                >
                    <img src="https://www.svgrepo.com/show/475656/google-color.svg" class="w-6 h-6 mr-3" />
                    Continuar com Google
                </button>

                <button 
                    class="flex items-center justify-center w-full h-14 bg-black text-white font-bold rounded-xl hover:bg-gray-900 border border-surface-700 transition-all shadow-lg active:scale-95"
                    @click="loginWithApple"
                >
                    <i class="pi pi-apple text-2xl mr-3"></i>
                    Continuar com Apple
                </button>

                <p class="text-xs text-center text-surface-500 mt-4">
                    Ao continuar, você concorda com nossos Termos de Uso.
                </p>
            </div>

            <div v-else class="flex flex-col gap-6 animate-fade-in">
                
                <div class="flex items-center justify-center -mt-2 mb-2">
                    <div class="relative">
                        <img :src="userStore.user?.photoUrl || 'https://www.gravatar.com/avatar/?d=mp'" class="w-20 h-20 rounded-full border-4 border-surface-800 shadow-xl" />
                        <div class="absolute bottom-0 right-0 bg-green-500 w-5 h-5 rounded-full border-2 border-surface-800"></div>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-bold text-surface-300 ml-1">Seu CPF</label>
                    <div class="relative">
                        <InputMask 
                            v-model="userStore.onboarding.cpf" 
                            mask="999.999.999-99" 
                            placeholder="000.000.000-00"
                            class="w-full !bg-surface-950 !border-surface-700 !text-white !h-12 !text-lg !tracking-wider !text-center !rounded-xl focus:!border-primary-500 focus:!ring-primary-500/50"
                            :class="{'!border-red-500': cpfError, '!border-green-500': userStore.onboarding.fullName}"
                            @blur="handleCpfBlur"
                            @keydown.enter="handleCpfBlur"
                        />
                        <div class="absolute right-4 top-1/2 -translate-y-1/2">
                            <i v-if="userStore.isLoadingGovApi" class="pi pi-spin pi-spinner text-primary-500"></i>
                            <i v-else-if="userStore.onboarding.fullName" class="pi pi-check-circle text-green-500 text-xl"></i>
                        </div>
                    </div>
                    <small v-if="cpfError" class="text-red-500 text-xs text-center">CPF inválido ou não encontrado.</small>

                    <Button 
                        v-if="!userStore.onboarding.fullName"
                        label="VALIDAR CPF" 
                        icon="pi pi-search"
                        class="w-full mt-2 h-12 !bg-surface-800 hover:!bg-surface-700 !border-surface-700 !text-white !font-bold rounded-xl transition-colors" 
                        :loading="userStore.isLoadingGovApi"
                        @click="handleCpfBlur"
                    />
                </div>

                <div v-if="userStore.onboarding.fullName" class="animate-scale-in">
                    
                    <div class="bg-surface-800 rounded-xl p-4 border border-surface-700 flex flex-col gap-3 mb-6 relative overflow-hidden">
                        <i class="pi pi-id-card absolute -right-4 -bottom-4 text-6xl text-surface-700/50 rotate-12"></i>

                        <div class="relative z-10">
                            <label class="text-xs uppercase tracking-wider font-bold text-surface-500">Nome Civil</label>
                            <div class="text-white font-bold text-lg leading-tight">{{ userStore.onboarding.fullName }}</div>
                        </div>

                        <div class="relative z-10 flex items-center gap-2">
                            <i class="pi pi-calendar text-primary-500"></i>
                            <span class="text-surface-300 text-sm">Nascido em <strong>{{ formatDate(userStore.onboarding.birthDate) }}</strong></span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 mb-6">
                        <label class="text-sm font-bold text-surface-300 ml-1">WhatsApp para Contato</label>
                        <div class="flex h-12">
                            <Dropdown 
                                v-model="userStore.onboarding.ddi" 
                                :options="ddiOptions" 
                                optionLabel="code" 
                                optionValue="code" 
                                class="!w-[90px] !rounded-r-none !bg-surface-800 !border-surface-700 !border-r-0"
                                :pt="{
                                    input: { class: '!text-white !font-bold flex items-center justify-center !p-0 !h-full' },
                                    trigger: { class: '!hidden' } 
                                }"
                            >
                                <template #value="slotProps">
                                    <div class="flex items-center justify-center w-full h-full">
                                        <span class="text-2xl leading-none pt-1">{{ getFlag(slotProps.value) }}</span>
                                    </div>
                                </template>
                            </Dropdown>
                            
                            <InputMask 
                                v-model="userStore.onboarding.whatsapp" 
                                mask="(99) 99999-9999" 
                                placeholder="(00) 00000-0000"
                                class="flex-1 !rounded-l-none !bg-surface-950 !border-surface-700 !text-white !h-full !text-lg"
                                :class="{'!border-green-500': isPhoneValid}"
                            />
                        </div>
                    </div>

                    <Button 
                        label="CONFIRMAR E CONTINUAR" 
                        icon="pi pi-check"
                        class="w-full h-14 !bg-green-500 hover:!bg-green-400 !border-none !text-white !font-bold !text-lg !rounded-xl shadow-lg shadow-green-500/20 disabled:!bg-surface-700 disabled:!text-surface-500"
                        :loading="isLoading"
                        :disabled="!isPhoneValid"
                        @click="finishOnboarding"
                    />

                </div>

            </div>

        </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useUserStore } from '@/stores/user-store';
import { useRouter } from 'vue-router';
import { notify } from '@/services/notify';
import InputMask from 'primevue/inputmask';
import Dropdown from 'primevue/dropdown';
import Button from 'primevue/button';

const userStore = useUserStore();
const router = useRouter();
const isLoading = ref(false);
const cpfError = ref(false);

const ddiOptions = [
    { code: '+55', label: 'Brasil', flag: '🇧🇷' },
    { code: '+1', label: 'EUA', flag: '🇺🇸' },
    { code: '+351', label: 'Portugal', flag: '🇵🇹' },
];

const getFlag = (code) => ddiOptions.find(o => o.code === code)?.flag || '🌐';

const formatDate = (dateString) => {
    if (!dateString) return '';
    const [year, month, day] = dateString.split('-');
    return `${day}/${month}/${year}`;
}

// Lógica de Validação do Telefone
const isPhoneValid = computed(() => {
    const phone = userStore.onboarding.whatsapp;
    if (!phone) return false;
    
    // Remove tudo que não é número
    const clean = phone.replace(/\D/g, '');
    
    // Aceita:
    // 10 dígitos (Fixo): (47) 3443-0000
    // 11 dígitos (Celular): (47) 99999-0000
    return clean.length >= 10;
});

// Simulação de Login Social
const loginWithGoogle = () => {
    userStore.user = {
        name: 'Usuário Google',
        email: 'usuario@gmail.com',
        photoUrl: 'https://lh3.googleusercontent.com/a/default-user=s96-c' 
    };
    userStore.isAuthenticated = true; 
    notify('success', 'Google', 'Autenticado com sucesso!');
};

const loginWithApple = () => {
    userStore.user = {
        name: 'Usuário Apple',
        email: 'usuario@icloud.com',
        photoUrl: null 
    };
    userStore.isAuthenticated = true;
    notify('success', 'Apple', 'Autenticado com sucesso!');
};

const handleCpfBlur = async () => {
    if (!userStore.onboarding.cpf) return;
    
    cpfError.value = false;
    
    const success = await userStore.validateAndEnrichCPF();
    
    if (!success) {
        cpfError.value = true;
        userStore.onboarding.fullName = ''; 
    }
};

const finishOnboarding = async () => {
    isLoading.value = true;
    await userStore.completeRegistration();
    
    notify('success', 'Tudo pronto!', 'Cadastro finalizado.');
    isLoading.value = false;
    router.push('/delivery');
};
</script>

<style scoped>
/* Animações de entrada */
.animate-fade-in-up {
    animation: fadeInUp 0.5s ease-out;
}
.animate-scale-in {
    animation: scaleIn 0.3s ease-out;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes scaleIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
</style>