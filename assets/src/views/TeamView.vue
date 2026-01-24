<template>
  <div class="p-6 w-full h-full bg-surface-950 overflow-hidden flex flex-col">
    
    <div class="flex justify-between items-center mb-6 shrink-0">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fa-solid fa-id-badge text-primary-500"></i> Gestão de Equipe
            </h1>
            <p class="text-surface-400 text-sm mt-1">Gerencie membros e permissões de acesso.</p>
        </div>
    </div>

    <div class="flex-1 bg-surface-900 border border-surface-800 rounded-2xl overflow-hidden flex flex-col shadow-xl">
        
        <TabView class="flex-1 flex flex-col min-h-0" 
            :pt="{ 
                nav: { class: '!bg-surface-950 !border-b !border-surface-800 !px-4' },
                panelContainer: { class: '!bg-surface-900 !p-0 !flex-1 !overflow-hidden !h-full' } 
            }"
        >
            
            <TabPanel header="Colaboradores" :pt="{ root: { class: '!h-full' } }">
                <div class="h-full flex flex-col p-6 overflow-hidden">
                    
                    <div class="flex justify-end mb-6 shrink-0">
                        <Button label="Novo Membro" icon="fa-solid fa-plus" class="!bg-primary-600 hover:!bg-primary-500 !border-none !font-bold !rounded-xl shadow-lg shadow-primary-900/20" @click="openModal()" />
                    </div>

                    <div class="flex-1 overflow-y-auto scrollbar-thin">
                        <div v-if="store.isLoading" class="flex justify-center p-20">
                            <i class="fa-solid fa-spinner fa-spin text-4xl text-primary-500"></i>
                        </div>

                        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5 pb-6">
                            
                            <div v-for="member in store.members" :key="member.id" 
                                 class="bg-surface-950 border border-surface-800 rounded-2xl p-5 flex flex-col gap-4 group hover:border-primary-500/50 transition-all duration-300 shadow-lg relative overflow-hidden hover:-translate-y-1"
                            >
                                <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-bl from-surface-800/30 to-transparent rounded-bl-[4rem] pointer-events-none group-hover:from-primary-500/10 transition-colors"></div>

                                <div class="flex justify-between items-start relative z-10">
                                    <div class="flex items-center gap-4">
                                        <div class="w-14 h-14 rounded-full bg-surface-900 flex items-center justify-center text-xl font-bold text-surface-400 border-2 border-surface-800 overflow-hidden shrink-0 shadow-inner group-hover:border-primary-500/30 transition-colors">
                                            <img v-if="member.avatar" :src="member.avatar" class="w-full h-full object-cover">
                                            <span v-else>{{ member.name.charAt(0).toUpperCase() }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold text-white text-lg truncate pr-2 leading-tight">{{ member.name }}</div>
                                            <div class="text-xs text-surface-500 truncate mt-0.5">{{ member.email }}</div>
                                        </div>
                                    </div>
                                    <Button icon="fa-solid fa-pen" text rounded class="!text-surface-500 hover:!text-white !w-8 !h-8 !bg-surface-900 hover:!bg-surface-800" @click="openModal(member)" />
                                </div>

                                <div class="bg-surface-900/80 backdrop-blur-sm rounded-xl p-3 border border-surface-800/50 relative z-10 min-h-[70px]">
                                    <div class="text-[10px] uppercase font-bold text-surface-500 mb-2 tracking-wider">Acessos</div>
                                    <div class="flex flex-wrap gap-2">
                                        <span v-for="role in member.roles" :key="role" 
                                              class="text-[10px] font-bold px-2.5 py-1 rounded-lg border flex items-center gap-1.5 transition-colors"
                                              :class="getRoleBadgeClass(role)">
                                            <i :class="getRoleIcon(role)"></i> {{ getRoleName(role) }}
                                        </span>
                                        <span v-if="!member.roles || member.roles.length === 0" class="text-[10px] text-surface-600 italic">
                                            Sem acesso definido
                                        </span>
                                    </div>
                                </div>

                                <div class="mt-auto pt-3 flex justify-between items-center relative z-10 border-t border-surface-800/50">
                                    <div class="flex items-center gap-2">
                                         <span v-if="member.pin" class="flex items-center text-green-400 font-bold bg-green-500/10 px-2 py-0.5 rounded text-[10px] border border-green-500/20">
                                            <i class="fa-solid fa-key mr-1.5 text-[8px]"></i> PIN
                                        </span>
                                        <span v-else class="text-surface-600 flex items-center bg-surface-900 px-2 py-0.5 rounded text-[10px] border border-surface-800">
                                            <i class="fa-solid fa-lock-open mr-1.5 text-[8px]"></i> Sem PIN
                                        </span>
                                    </div>
                                    <span class="text-[9px] text-surface-600 font-mono">ID: {{ member.id }}</span>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </TabPanel>

            <TabPanel header="Papéis e Acessos" :pt="{ root: { class: '!h-full' } }">
                <div class="flex flex-col h-full overflow-hidden bg-surface-900 relative">
                    
                    <div class="p-6 pb-4 shrink-0 bg-surface-900 z-10">
                        <div class="bg-primary-500/10 border border-primary-500/20 p-4 rounded-xl text-sm text-primary-200 flex gap-3 items-start">
                            <i class="fa-solid fa-circle-info mt-0.5 text-primary-500"></i>
                            <div>
                                <strong>Controle de Acesso Granular:</strong> Marque o que cada cargo pode fazer. As alterações afetam todos os usuários imediatamente.
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 overflow-auto scrollbar-thin px-6 pb-6 relative">
                        <div v-if="isLoadingMatrix" class="flex justify-center p-10"><i class="fa-solid fa-spinner fa-spin text-2xl text-surface-500"></i></div>
                        
                        <table v-else class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-surface-900 z-20 shadow-sm">
                                <tr>
                                    <th class="p-4 border-b border-surface-700 text-surface-400 font-bold uppercase text-xs tracking-wider w-1/3 bg-surface-900">Funcionalidade</th>
                                    <th v-for="role in matrixData" :key="role.slug" class="p-4 border-b border-surface-700 text-center min-w-[120px] bg-surface-900">
                                        <div class="font-bold text-white">{{ role.name }}</div>
                                        <div class="text-[9px] text-surface-500 font-mono">{{ role.slug }}</div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-800">
                                <tr v-for="feat in schema" :key="feat.cap" class="hover:bg-surface-800/30 transition-colors">
                                    <td class="p-4">
                                        <div class="font-bold text-surface-200 text-sm mb-0.5">{{ feat.label }}</div>
                                        <div class="text-xs text-surface-500 leading-snug">{{ feat.desc }}</div>
                                    </td>
                                    <td v-for="role in matrixData" :key="role.slug" class="p-4 text-center align-middle">
                                        <div class="flex justify-center">
                                            <Checkbox v-model="role.caps[feat.cap]" :binary="true" />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t border-surface-800 bg-surface-900 shrink-0 flex justify-end">
                        <Button label="Salvar Permissões" icon="fa-solid fa-save" class="!bg-primary-600 hover:!bg-primary-500 !border-none !font-bold shadow-lg shadow-primary-900/20" :loading="isSavingMatrix" @click="saveMatrix" />
                    </div>
                </div>
            </TabPanel>

        </TabView>
    </div>

    <Dialog 
        v-model:visible="showModal" 
        modal 
        :header="form.id ? 'Editar Colaborador' : 'Adicionar Colaborador'" 
        :style="{ width: '550px' }" 
        :pt="{ 
            root: { class: '!bg-surface-900 !border !border-surface-700 !rounded-2xl !shadow-2xl' }, 
            header: { class: '!bg-surface-950 !text-white !border-b !border-surface-800 !p-6' }, 
            content: { class: '!bg-surface-900 !p-6' },
            closeButton: { class: '!text-surface-400 hover:!text-white' }
        }"
    >
        <div class="flex flex-col gap-5">
            <div v-if="!form.id" class="bg-primary-500/10 border border-primary-500/20 p-4 rounded-xl text-xs text-primary-300 flex gap-3">
                <i class="fa-solid fa-circle-info text-lg mt-0.5"></i>
                <div class="leading-relaxed">
                    <strong>Dica:</strong> Digite o e-mail de um cliente existente para transformá-lo em colaborador.
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-bold text-surface-300">E-mail (Login)</label>
                    <div class="relative">
                        <i class="fa-solid fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-surface-500"></i>
                        <InputText v-model="form.email" class="!bg-surface-950 !border-surface-700 !text-white !pl-10 !w-full !rounded-xl !h-11" :disabled="!!form.id" placeholder="email@exemplo.com" />
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-bold text-surface-300">Nome</label>
                    <div class="relative">
                        <i class="fa-solid fa-user absolute left-4 top-1/2 -translate-y-1/2 text-surface-500"></i>
                        <InputText v-model="form.name" class="!bg-surface-950 !border-surface-700 !text-white !pl-10 !w-full !rounded-xl !h-11" placeholder="Nome Completo" />
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-bold text-surface-300">Atribuições (Múltipla Escolha)</label>
                <div class="grid grid-cols-1 gap-2 bg-surface-950 p-2 rounded-xl border border-surface-800 max-h-60 overflow-y-auto scrollbar-thin">
                    <div v-for="roleDef in availableRoles" :key="roleDef.value"
                        class="flex items-center p-3 rounded-lg border transition-all cursor-pointer group hover:bg-surface-800"
                        :class="form.roles.includes(roleDef.value) ? 'bg-primary-500/10 border-primary-500 shadow-inner' : 'bg-transparent border-surface-800'"
                        @click="toggleRole(roleDef.value)"
                    >
                        <div class="w-5 h-5 rounded border flex items-center justify-center mr-3 transition-colors shrink-0"
                             :class="form.roles.includes(roleDef.value) ? 'bg-primary-500 border-primary-500' : 'border-surface-600 group-hover:border-surface-400'">
                            <i v-if="form.roles.includes(roleDef.value)" class="fa-solid fa-check text-surface-900 text-[10px] font-bold"></i>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-surface-900 border border-surface-700 flex items-center justify-center mr-3 text-surface-400 shrink-0">
                            <i class="fa-solid text-lg" :class="roleDef.icon"></i>
                        </div>
                        <div class="flex-1">
                            <div class="font-bold text-sm text-white">{{ roleDef.label }}</div>
                            <div class="text-[10px] text-surface-500">{{ roleDef.desc }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-bold text-surface-300 flex justify-between">
                    <span>PIN de Acesso Rápido (PDV)</span>
                    <span class="text-[10px] text-surface-500 uppercase tracking-wider font-normal">Opcional</span>
                </label>
                <div class="relative">
                    <InputText v-model="form.pin" maxlength="4" placeholder="••••" class="!bg-surface-950 !border-surface-700 !text-white !text-center !tracking-[1em] !font-mono !text-xl !h-12 !rounded-xl !w-full focus:!border-primary-500" />
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-4 pt-6 border-t border-surface-800">
                <Button label="Cancelar" text class="!text-surface-400 hover:!text-white !font-bold" @click="showModal = false" />
                <Button label="Salvar Acessos" icon="fa-solid fa-check" class="!bg-primary-600 hover:!bg-primary-500 !border-none !font-bold !rounded-xl !px-6 shadow-lg shadow-primary-900/20" @click="save" :loading="isSaving" />
            </div>
        </div>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import { useTeamStore } from '@/stores/team-store';
import api from '@/services/api';
import { notify } from '@/services/notify';
import Button from 'primevue/button';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';

const store = useTeamStore();
const showModal = ref(false);
const isSaving = ref(false);

// ATUALIZADO: Definições claras de Papel para o Modal
const availableRoles = [
    { 
        label: 'Super Admin (Proprietário)', 
        value: 'administrator', 
        icon: 'fa-solid fa-crown', // Ícone de coroa para destacar
        desc: 'Acesso irrestrito a todo o sistema WordPress e Plugins.' 
    },
    { 
        label: 'Gestão (Gerente)', 
        value: 'nativa_manager', 
        icon: 'fa-solid fa-user-shield', 
        desc: 'Acesso total: Caixa (PDV), Financeiro, Auditoria e Configurações.' 
    },
    { 
        label: 'Atendimento (Garçom)', 
        value: 'nativa_waiter', 
        icon: 'fa-solid fa-mobile-screen-button', // Ícone de celular para reforçar o mobile 
        desc: 'Operacional: Comanda Digital (Mobile), Mesas e Delivery.' 
    },
    { 
        label: 'Entrega (Motoboy)', 
        value: 'nativa_driver', 
        icon: 'fa-solid fa-motorcycle', 
        desc: 'Logística: App de entregas e rotas.' 
    },
    { 
        label: 'Cozinha (KDS)', 
        value: 'nativa_kitchen', 
        icon: 'fa-solid fa-fire-burner', 
        desc: 'Produção: Tela KDS e fila de pedidos.' 
    }
];

const form = reactive({ id: null, name: '', email: '', roles: [], pin: '' });

// --- MATRIZ ---
const schema = ref([]);
const matrixData = ref([]);
const isLoadingMatrix = ref(false);
const isSavingMatrix = ref(false);

const fetchMatrix = async () => {
    isLoadingMatrix.value = true;
    try {
        const { data } = await api.get('/roles-matrix');
        if(data.success) {
            schema.value = data.schema;
            matrixData.value = data.matrix;
        }
    } catch(e) { 
        console.error(e); 
    } finally { 
        isLoadingMatrix.value = false; 
    }
};

const saveMatrix = async () => {
    isSavingMatrix.value = true;
    try {
        const { data } = await api.post('/roles-matrix', { matrix: matrixData.value });
        if(data.success) {
            notify('success', 'Sucesso', 'Permissões atualizadas globalmente.');
        } else {
            throw new Error(data.message || 'Erro ao salvar');
        }
    } catch(e) {
        notify('error', 'Erro', e.message || 'Falha ao salvar matriz.');
    } finally { 
        isSavingMatrix.value = false; 
    }
};

// --- CRUD ---
const getRoleIcon = (role) => availableRoles.find(r => r.value === role)?.icon || 'fa-solid fa-user';
const getRoleName = (role) => {
    if (role === 'administrator') return 'Super Admin';
    return availableRoles.find(r => r.value === role)?.label.split('(')[0].trim() || role;
};
const getRoleBadgeClass = (role) => {
    switch(role) {
        case 'administrator': return 'bg-purple-500/10 text-purple-400 border-purple-500/20';
        case 'nativa_manager': return 'bg-blue-500/10 text-blue-400 border-blue-500/20';
        case 'nativa_waiter': return 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20';
        case 'nativa_driver': return 'bg-green-500/10 text-green-400 border-green-500/20';
        case 'nativa_kitchen': return 'bg-orange-500/10 text-orange-400 border-orange-500/20';
        default: return 'bg-surface-800 text-surface-400 border-surface-700';
    }
};

const toggleRole = (roleValue) => {
    const idx = form.roles.indexOf(roleValue);
    idx > -1 ? form.roles.splice(idx, 1) : form.roles.push(roleValue);
};

const openModal = (member = null) => {
    if (member) {
        form.id = member.id;
        form.name = member.name;
        form.email = member.email;
        form.pin = member.pin;
        form.roles = Array.isArray(member.roles) ? [...member.roles] : (member.role ? [member.role] : []);
    } else {
        Object.assign(form, { id: null, name: '', email: '', roles: ['nativa_waiter'], pin: '' });
    }
    showModal.value = true;
};

const save = async () => {
    if (!form.email || form.roles.length === 0) {
        notify('warn', 'Atenção', 'Preencha o e-mail e selecione ao menos um cargo.');
        return;
    }
    isSaving.value = true;
    const success = await store.saveMember(form);
    
    if (success) {
        showModal.value = false;
    }
    
    isSaving.value = false;
};

onMounted(() => {
    store.fetchTeam();
    fetchMatrix();
});
</script>