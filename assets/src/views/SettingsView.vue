<template>
  <div class="flex flex-col h-full w-full overflow-hidden bg-surface-1 rounded-[24px] transition-all">
    
    <div class="shrink-0 h-20 px-6 flex items-center justify-between z-20">
        <div>
            <h1 class="text-2xl font-black text-surface-0 flex items-center gap-3">
                <i class="fa-solid fa-cog text-primary-500"></i> Configurações da Loja
            </h1>
            <p class="text-surface-400 text-sm mt-1 font-bold">Gerencie horários, contatos e status.</p>
        </div>
        <div class="flex gap-3">
            <Button label="Salvar Tudo" icon="fa-solid fa-save" class="!bg-primary-600 hover:!bg-primary-500 !border-none !font-black !text-surface-950 !rounded-full !px-6" @click="save" :loading="saving" />
        </div>
    </div>

    <div class="flex-1 overflow-hidden flex flex-col">
        <div v-if="store.isLoading" class="flex justify-center p-20"><i class="fa-solid fa-spinner fa-spin text-4xl text-primary-500"></i></div>

        <div v-else-if="store.hours" class="flex-1 flex flex-col overflow-hidden">
            
            <div class="p-4 mx-6 mt-4 bg-surface-2 rounded-[20px] flex items-center justify-between">
                <div class="flex flex-col">
                    <span class="text-surface-0 font-black text-sm">Status Manual do Sistema</span>
                    <span class="text-surface-500 text-xs font-bold">Use em emergências ou feriados</span>
                </div>
                <SelectButton v-model="store.hours.manual_override" :options="overrideOptions" optionLabel="label" optionValue="value" 
                    :pt="{ 
                        root: { class: 'flex bg-surface-1 rounded-full p-1' },
                        button: ({context}) => ({ class: `!border-none !rounded-full !px-4 !py-2 !text-xs !font-black ${context.active ? '!bg-primary-500 !text-surface-950' : '!bg-transparent !text-surface-400'}` }) 
                    }" 
                />
            </div>
            
            <Tabs value="contact" class="flex-1 flex flex-col overflow-hidden mt-4">
                <TabList :pt="{ root: { class: '!bg-transparent !border-b !border-surface-3/20 !px-6' }, inkbar: { class: '!bg-primary-500 !h-1 !rounded-t-full' } }">
                    <Tab value="contact" class="!text-surface-400 data-[p-active=true]:!text-primary-500 !font-black !px-6 !py-4 !bg-transparent">Geral / Contato</Tab>
                    <Tab v-for="type in types" :key="type.id" :value="type.id" class="!text-surface-400 data-[p-active=true]:!text-primary-500 !font-black !px-6 !py-4 !bg-transparent">{{ type.label }}</Tab>
                    <Tab value="fiscal" class="!text-surface-400 data-[p-active=true]:!text-primary-500 !font-black !px-6 !py-4 !bg-transparent">Fiscal (NFC-e)</Tab>
                </TabList>

                <TabPanels :pt="{ root: { class: '!bg-transparent !p-0 !h-full !overflow-y-auto' } }">
                    
                    <TabPanel value="contact">
                        <div class="p-6 max-w-3xl">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="bg-surface-2 p-5 rounded-[20px]">
                                    <label class="text-sm font-black text-surface-400 block mb-2">WhatsApp de Atendimento</label>
                                    <div class="flex items-center gap-2">
                                        <i class="fa-brands fa-whatsapp text-green-500 text-xl"></i>
                                        <InputText v-model="store.contact.whatsapp" placeholder="Ex: 5547999999999" class="w-full !rounded-full" />
                                    </div>
                                </div>
                                <div class="bg-surface-2 p-5 rounded-[20px]">
                                    <label class="text-sm font-black text-surface-400 block mb-2">Endereço da Loja</label>
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-map-pin text-red-500 text-xl"></i>
                                        <InputText v-model="store.contact.address" placeholder="Ex: Rua Central, 100" class="w-full !rounded-full" />
                                    </div>
                                </div>
                                </div>
                        </div>
                    </TabPanel>

                    <TabPanel v-for="type in types" :key="type.id" :value="type.id">
                        <div class="p-6">
                            <div class="bg-primary-500/10 p-4 rounded-[20px] mb-6 flex items-start gap-3">
                                <i class="fa-solid fa-circle-info text-primary-500 mt-1"></i>
                                <div class="text-sm text-surface-200">
                                    <strong>{{ type.desc }}</strong><br>
                                    Configure os dias e intervalos.
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div v-for="(dayLabel, dayKey) in days" :key="dayKey" 
                                     class="bg-surface-2 rounded-[20px] p-4 flex flex-col gap-3 transition-colors border border-transparent"
                                     :class="!store.hours[type.id][dayKey].active ? 'opacity-50 grayscale' : 'border-primary-500/20'"
                                >
                                    <div class="flex justify-between items-center">
                                        <span class="font-black text-surface-0 uppercase">{{ dayLabel }}</span>
                                        <ToggleSwitch v-model="store.hours[type.id][dayKey].active" />
                                    </div>
                                    
                                    <div class="flex items-center gap-2" v-if="store.hours[type.id][dayKey].active">
                                        <div class="flex-1">
                                            <input type="time" v-model="store.hours[type.id][dayKey].start" class="w-full bg-surface-1 rounded-lg text-surface-0 px-2 py-1.5 font-bold outline-none text-center" />
                                        </div>
                                        <span class="text-surface-500">-</span>
                                        <div class="flex-1">
                                            <input type="time" v-model="store.hours[type.id][dayKey].end" class="w-full bg-surface-1 rounded-lg text-surface-0 px-2 py-1.5 font-bold outline-none text-center" />
                                        </div>
                                    </div>
                                    <div v-else class="h-[38px] flex items-center justify-center text-xs font-black text-surface-500 bg-surface-1 rounded-lg border border-dashed border-surface-3">
                                        FECHADO
                                    </div>
                                </div>
                            </div>
                        </div>
                    </TabPanel>

                    <TabPanel value="fiscal">
                        <div class="p-6 max-w-4xl mx-auto space-y-6">
                             <div class="bg-surface-2 p-6 rounded-[24px]">
                                 </div>
                        </div>
                    </TabPanel>

                </TabPanels>
            </Tabs>
        </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import { useSettingsStore } from '@/stores/settings-store';
import api from '@/services/api'; 
import Button from 'primevue/button';
import ToggleSwitch from 'primevue/toggleswitch';
import SelectButton from 'primevue/selectbutton';
import InputText from 'primevue/inputtext';
import InputMask from 'primevue/inputmask';
import Password from 'primevue/password';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import { notify } from '@/services/notify';

const store = useSettingsStore();
const saving = ref(false);

const types = [
    { id: 'general', label: 'Horários Gerais', desc: 'Define o funcionamento global.' },
    { id: 'delivery', label: 'Delivery', desc: 'Horário específico para entregas.' },
    { id: 'pickup', label: 'Retirada', desc: 'Horário para retirada no balcão.' }
];

const days = { mon: 'Segunda', tue: 'Terça', wed: 'Quarta', thu: 'Quinta', fri: 'Sexta', sat: 'Sábado', sun: 'Domingo' };

const overrideOptions = [
    { label: 'Automático', value: 'auto' },
    { label: 'Forçar Aberto', value: 'force_open' },
    { label: 'Forçar Fechado', value: 'force_closed' }
];

const save = async () => {
    saving.value = true;
    await store.saveAll();
    saving.value = false;
};

// --- LÓGICA FISCAL ---
const fiscal = reactive({
    razao_social: '',
    cnpj: '',
    ie: '',
    csc_id: '',
    csc_token: '',
    cert_password: '',
    environment: 'homologation',
    has_certificate: false
});
const fileName = ref(null);
const fileData = ref(null);
const savingFiscal = ref(false);
const certFile = ref(null); 

const handleFileSelect = (event) => {
    const file = event.target.files[0];
    if (file) {
        fileName.value = file.name;
        fileData.value = file;
    }
};

const fetchFiscal = async () => {
    try {
        const { data } = await api.get('/settings/fiscal');
        if (data.success) {
            Object.assign(fiscal, data.fiscal);
        }
    } catch (e) {
        console.error('Erro ao carregar fiscal', e);
    }
};

const saveFiscal = async () => {
    savingFiscal.value = true;
    const formData = new FormData();
    
    Object.keys(fiscal).forEach(key => {
        if (fiscal[key] !== null && key !== 'has_certificate') {
            formData.append(key, fiscal[key]);
        }
    });

    if (fileData.value) {
        formData.append('pfx_file', fileData.value);
    }

    try {
        const { data } = await api.post('/settings/fiscal', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        
        if (data.success) {
            notify('success', 'Sucesso', 'Dados fiscais atualizados.');
            fiscal.has_certificate = true; 
            fileName.value = null;
            fileData.value = null;
        }
    } catch (e) {
        notify('error', 'Erro', 'Falha ao salvar dados fiscais.');
    } finally {
        savingFiscal.value = false;
    }
};

onMounted(() => {
    store.fetchAllSettings();
    fetchFiscal(); 
});
</script>