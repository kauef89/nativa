<template>
  <div class="p-6 w-full h-full bg-surface-950 overflow-hidden flex flex-col">
    
    <div class="flex justify-between items-center mb-6 shrink-0">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fa-solid fa-id-badge text-primary-500"></i> Equipe e Acessos
            </h1>
            <p class="text-surface-400 text-sm mt-1">Gerencie quem acessa o sistema e suas funções.</p>
        </div>
        <Button label="Adicionar / Promover" icon="fa-solid fa-plus" @click="openModal()" />
    </div>

    <div class="flex-1 overflow-y-auto scrollbar-thin">
        <div v-if="store.isLoading" class="flex justify-center p-20">
            <i class="fa-solid fa-spinner fa-spin text-4xl text-primary-500"></i>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            
            <div v-for="member in store.members" :key="member.id" 
                 class="bg-surface-900 border border-surface-800 rounded-2xl p-5 flex flex-col gap-4 group hover:border-primary-500/50 transition-colors shadow-lg relative overflow-hidden"
            >
                <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-bl from-surface-800 to-transparent opacity-50 rounded-bl-full pointer-events-none"></div>

                <div class="flex justify-between items-start relative z-10">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full bg-surface-800 flex items-center justify-center text-xl font-bold text-surface-400 border border-surface-700 overflow-hidden">
                            <img v-if="member.avatar" :src="member.avatar" class="w-full h-full object-cover">
                            <span v-else>{{ member.name.charAt(0).toUpperCase() }}</span>
                        </div>
                        <div class="min-w-0">
                            <div class="font-bold text-white text-lg truncate pr-2">{{ member.name }}</div>
                            <div class="text-xs text-surface-400 truncate">{{ member.email }}</div>
                        </div>
                    </div>
                    <Button icon="fa-solid fa-pen" text rounded class="!text-surface-500 hover:!text-white" @click="openModal(member)" />
                </div>

                <div class="bg-surface-950/50 rounded-xl p-3 border border-surface-800/50 relative z-10">
                    <div class="text-[10px] uppercase font-bold text-surface-500 mb-1">Função Atual</div>
                    <div class="flex items-center gap-2">
                        <i class="fa-solid" :class="getRoleIcon(member.role)"></i>
                        <span class="font-bold text-primary-400 text-sm">{{ member.role_label }}</span>
                    </div>
                </div>

                <div class="mt-auto pt-2 text-xs text-surface-500 flex justify-between items-center relative z-10">
                    <span v-if="member.pin" class="flex items-center text-green-400 font-bold bg-green-500/10 px-2 py-0.5 rounded">
                        <i class="fa-solid fa-key mr-1.5 text-[10px]"></i> PIN
                    </span>
                    <span v-else class="text-surface-600">Sem PIN</span>
                    
                    <span class="opacity-50">ID #{{ member.id }}</span>
                </div>
            </div>

        </div>
    </div>

    <Dialog v-model:visible="showModal" modal header="Dados do Colaborador" :style="{ width: '450px' }" 
            :pt="{ root: { class: '!bg-surface-900 !border-surface-700' }, header: { class: '!bg-surface-950 !text-white' }, content: { class: '!bg-surface-900' } }">
        <div class="flex flex-col gap-4 mt-2">
            
            <div class="bg-primary-500/10 border border-primary-500/20 p-3 rounded-lg text-xs text-primary-300 flex gap-2">
                <i class="fa-solid fa-circle-info mt-0.5"></i>
                <span>Para adicionar alguém que já usa o app (Login Social), basta digitar o e-mail exato dele abaixo.</span>
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-bold text-surface-300">E-mail (Login Social)</label>
                <InputText v-model="form.email" class="!bg-surface-950 !border-surface-700 !text-white" :disabled="!!form.id" placeholder="ex: joao@gmail.com" />
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-bold text-surface-300">Nome de Exibição</label>
                <InputText v-model="form.name" class="!bg-surface-950 !border-surface-700 !text-white" />
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-bold text-surface-300">Papel / Função</label>
                <Select 
                    v-model="form.role" 
                    :options="roles" 
                    optionLabel="label" 
                    optionValue="value" 
                    class="!bg-surface-950 !border-surface-700 w-full" 
                    :pt="{ 
                        label: { class: '!text-white' }, 
                        overlay: { class: '!bg-surface-900 !border-surface-700' },
                        option: { class: '!text-surface-200 hover:!bg-surface-800' } 
                    }" 
                />
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-bold text-surface-300">PIN de Acesso Rápido (4 dígitos)</label>
                <InputText v-model="form.pin" maxlength="4" placeholder="****" class="!bg-surface-950 !border-surface-700 !text-white !text-center !tracking-[0.5em] !font-mono" />
                <small class="text-surface-500">Usado para troca rápida de operador no PDV.</small>
            </div>

            <div class="flex justify-end gap-2 mt-4 pt-4 border-t border-surface-800">
                <Button label="Cancelar" text class="!text-surface-400 hover:!text-white" @click="showModal = false" />
                <Button label="Salvar Acesso" icon="fa-solid fa-check" class="!bg-primary-600 hover:!bg-primary-500 !border-none" @click="save" :loading="isSaving" />
            </div>
        </div>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import { useTeamStore } from '@/stores/team-store';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select'; // <--- IMPORT ATUALIZADO (ERA DROPDOWN)

const store = useTeamStore();
const showModal = ref(false);
const isSaving = ref(false);

const roles = [
    { label: 'Gestão (Admin)', value: 'nativa_manager' },
    { label: 'Atendimento (Garçom)', value: 'nativa_waiter' },
    { label: 'Cozinha (KDS)', value: 'nativa_kitchen' },
    { label: 'Entrega (Delivery)', value: 'nativa_driver' }
];

const form = reactive({ id: null, name: '', email: '', role: 'nativa_waiter', pin: '' });

const getRoleIcon = (role) => {
    switch(role) {
        case 'nativa_waiter': return 'fa-user-tie text-blue-400';
        case 'nativa_manager': return 'fa-user-shield text-purple-400';
        case 'nativa_kitchen': return 'fa-fire-burner text-orange-400';
        case 'nativa_driver': return 'fa-motorcycle text-green-400';
        default: return 'fa-user text-surface-400';
    }
};

const openModal = (member = null) => {
    if (member) {
        Object.assign(form, member);
    } else {
        Object.assign(form, { id: null, name: '', email: '', role: 'nativa_waiter', pin: '' });
    }
    showModal.value = true;
};

const save = async () => {
    isSaving.value = true;
    const success = await store.saveMember(form);
    isSaving.value = false;
    if (success) showModal.value = false;
};

onMounted(() => store.fetchTeam());
</script>