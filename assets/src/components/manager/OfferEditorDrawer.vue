<template>
    <Drawer 
        v-model:visible="isVisible" 
        position="right" 
        :pt="{ 
            root: { class: '!w-full md:!w-[700px] !bg-surface-950 !border-l !border-surface-800' },
            header: { class: '!bg-surface-900 !border-b !border-surface-800 !p-6' }, 
            content: { class: '!p-0' },
            mask: { class: '!bg-black/50 backdrop-blur-sm' }
        }"
    >
        <template #header>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-primary-500/10 flex items-center justify-center text-primary-500">
                    <i class="fa-solid fa-bullhorn"></i>
                </div>
                <div>
                    <h2 class="font-bold text-white text-lg">{{ offerId ? 'Editar Oferta' : 'Nova Oferta' }}</h2>
                    <p class="text-xs text-surface-400">Sistema de Lógica Avançada</p>
                </div>
            </div>
        </template>

        <div class="flex flex-col h-full overflow-y-auto p-6 space-y-8">
            
            <section class="space-y-4">
                <h3 class="text-xs font-bold text-surface-500 uppercase tracking-widest border-b border-surface-800 pb-2">
                    1. Configuração do Produto
                </h3>
                <div class="space-y-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-bold text-surface-300">Produto a ser Ofertado</label>
                        <Select 
                            v-model="form.produto_ofertado" 
                            :options="products" 
                            optionLabel="name" 
                            optionValue="id" 
                            placeholder="Selecione..." 
                            filter
                            class="w-full"
                            :pt="{ 
                                label: { class: '!text-white' },
                                option: { class: 'hover:!bg-surface-800 focus:!bg-surface-800 !text-surface-200' },
                                filterInput: { class: '!bg-surface-950 !border-surface-700 !text-white' }
                            }"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-bold text-surface-300">Preço Oferta</label>
                            <InputNumber 
                                v-model="form.preco_promocional" 
                                mode="currency" 
                                currency="BRL" 
                                locale="pt-BR" 
                                class="w-full" 
                            />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-bold text-surface-300">Chamada</label>
                            <InputText v-model="form.texto_da_oferta" class="w-full !text-white" />
                        </div>
                    </div>
                    <div class="flex items-center justify-between bg-surface-900 p-3 rounded-lg border border-surface-800">
                        <span class="text-sm font-bold text-surface-300">Status da Oferta</span>
                        <ToggleSwitch v-model="form.oferta_status" />
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <div class="flex justify-between items-end border-b border-surface-800 pb-2">
                    <div>
                        <h3 class="text-xs font-bold text-surface-500 uppercase tracking-widest">2. Regras de Ativação</h3>
                        <p class="text-[10px] text-surface-400 mt-1">A oferta ativa se <strong>QUALQUER</strong> grupo for verdadeiro.</p>
                    </div>
                    <Button 
                        label="Novo Grupo (OU)" 
                        icon="fa-solid fa-layer-group" 
                        size="small" 
                        class="!bg-surface-800 hover:!bg-surface-700 !text-primary-400 !border-none !text-xs" 
                        @click="addGroup" 
                    />
                </div>

                <div v-if="form.grupos.length === 0" class="text-center py-8 border-2 border-dashed border-surface-800 rounded-xl bg-surface-900/30">
                    <i class="fa-solid fa-unlock text-surface-600 text-2xl mb-2"></i>
                    <p class="text-surface-500 text-sm">Sem regras definidas.<br>Oferta aparecerá para todos.</p>
                </div>

                <div class="space-y-6">
                    <div v-for="(group, gIndex) in form.grupos" :key="gIndex" class="bg-surface-900 border border-surface-700 rounded-xl overflow-hidden relative">
                        
                        <div class="bg-surface-800 px-4 py-2 flex justify-between items-center border-b border-surface-700">
                            <span class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-2">
                                <span class="bg-surface-700 w-5 h-5 flex items-center justify-center rounded-full text-[10px]">{{ gIndex + 1 }}</span>
                                Grupo de Condições (E)
                            </span>
                            <div class="flex gap-2">
                                <Button icon="fa-solid fa-plus" text rounded size="small" class="!w-6 !h-6 !text-green-400" v-tooltip="'Adicionar Regra'" @click="addRuleToGroup(gIndex)" />
                                <Button icon="fa-solid fa-trash" text rounded size="small" class="!w-6 !h-6 !text-red-400" v-tooltip="'Remover Grupo'" @click="removeGroup(gIndex)" />
                            </div>
                        </div>

                        <div class="p-3 space-y-2">
                            <div v-if="group.regras.length === 0" class="text-center text-xs text-surface-500 py-2 italic">
                                Nenhuma regra neste grupo (sempre verdadeiro).
                            </div>

                            <div v-for="(rule, rIndex) in group.regras" :key="rIndex" class="flex gap-2 items-center bg-surface-950 p-2 rounded-lg border border-surface-800">
                                
                                <Select 
                                    v-model="rule.tipo_regra" 
                                    :options="ruleTypes" 
                                    optionLabel="label" 
                                    optionValue="value" 
                                    class="w-1/3 !h-8 text-xs"
                                    :pt="{ 
                                        label: { class: '!text-white !text-xs !py-1.5' },
                                        option: { class: '!text-sm' }
                                    }"
                                    @change="resetRuleValue(rule)"
                                />

                                <Select 
                                    v-model="rule.operador" 
                                    :options="getOperators(rule.tipo_regra)" 
                                    optionLabel="label" 
                                    optionValue="value" 
                                    class="w-1/4 !h-8 text-xs"
                                    :pt="{ 
                                        label: { class: '!text-white !text-xs !py-1.5' },
                                        option: { class: '!text-sm' }
                                    }"
                                />

                                <div class="flex-1">
                                    <InputNumber 
                                        v-if="rule.tipo_regra === 'subtotal_carrinho'"
                                        v-model="rule.valor" 
                                        mode="currency" currency="BRL" locale="pt-BR"
                                        class="w-full !h-8" :pt="{ input: { class: '!text-white !text-xs !py-1' } }"
                                    />
                                    
                                    <InputNumber 
                                        v-else-if="rule.tipo_regra === 'total_itens_carrinho'"
                                        v-model="rule.valor" 
                                        showButtons :min="0" :step="1"
                                        class="w-full !h-8" 
                                        inputClass="!text-white !text-xs !py-1 !text-center"
                                        decrementButtonClass="!bg-surface-800 !text-surface-400 !w-6" 
                                        incrementButtonClass="!bg-surface-800 !text-surface-400 !w-6"
                                    />

                                    <Select 
                                        v-else-if="rule.tipo_regra === 'categoria_no_carrinho'"
                                        v-model="rule.valor_categoria" 
                                        :options="categories" optionLabel="name" optionValue="id" 
                                        class="w-full !h-8"
                                        placeholder="Categoria"
                                        :pt="{ 
                                            label: { class: '!text-white !text-xs !py-1.5' },
                                            option: { class: '!text-sm' }
                                        }"
                                    />
                                    
                                    <Select 
                                        v-else-if="rule.tipo_regra === 'produto_no_carrinho'"
                                        v-model="rule.valor_produto" 
                                        :options="products" optionLabel="name" optionValue="id" filter
                                        class="w-full !h-8"
                                        placeholder="Produto"
                                        :pt="{ 
                                            label: { class: '!text-white !text-xs !py-1.5' },
                                            option: { class: '!text-sm' }
                                        }"
                                    />
                                </div>

                                <button class="text-surface-500 hover:text-red-400 px-2" @click="removeRuleFromGroup(gIndex, rIndex)">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div v-if="gIndex < form.grupos.length - 1" class="absolute -bottom-3 left-1/2 -translate-x-1/2 bg-surface-950 text-surface-500 text-[10px] font-bold px-2 py-0.5 rounded border border-surface-800 z-10">
                            OU
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <template #footer>
            <div class="p-4 bg-surface-900 border-t border-surface-800 flex justify-end gap-3">
                <Button label="Cancelar" text class="!text-surface-400 hover:!text-white" @click="isVisible = false" />
                <Button 
                    label="Salvar" 
                    icon="fa-solid fa-check" 
                    class="!bg-primary-600 hover:!bg-primary-500 !border-none !text-surface-950 !font-black" 
                    :loading="saving" 
                    @click="saveOffer" 
                />
            </div>
        </template>
    </Drawer>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { useMenuStore } from '@/stores/menu-store';
import { useProductsStore } from '@/stores/products-store';
import axios from 'axios';
import { notify } from '@/services/notify';

// UI Components
import Drawer from 'primevue/drawer';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select'; 
import ToggleSwitch from 'primevue/toggleswitch'; 

const props = defineProps(['modelValue', 'offerToEdit']);
const emit = defineEmits(['update:modelValue', 'saved']);

const menuStore = useMenuStore();
const productsStore = useProductsStore();

const isVisible = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

const saving = ref(false);
const offerId = ref(null);

const form = reactive({
    produto_ofertado: null,
    preco_promocional: 0,
    limite_total_usos: 0,
    texto_da_oferta: '',
    oferta_status: true,
    grupos: []
});

const products = computed(() => productsStore.products);
const categories = computed(() => menuStore.categories);

// --- TIPOS DE REGRA ---
const ruleTypes = [
    { label: 'Subtotal R$', value: 'subtotal_carrinho' },
    { label: 'Qtd. Itens', value: 'total_itens_carrinho' },
    { label: 'Categoria', value: 'categoria_no_carrinho' },
    { label: 'Produto', value: 'produto_no_carrinho' }
];

// --- OPERADORES DINÂMICOS ---
const getOperators = (type) => {
    if (type === 'subtotal_carrinho' || type === 'total_itens_carrinho') {
        return [
            { label: '>= (Maior ou Igual)', value: 'maior_igual' },
            { label: '<= (Menor ou Igual)', value: 'menor_igual' },
            { label: '> (Maior que)', value: 'maior' },
            { label: '< (Menor que)', value: 'menor' },
            { label: '= (Igual)', value: 'igual' },
            { label: '!= (Diferente)', value: 'diferente' }
        ];
    }
    return [
        { label: 'Contém', value: 'contem' },
        { label: 'Não Contém', value: 'nao_contem' }
    ];
};

const addGroup = () => {
    form.grupos.push({ regras: [] });
    addRuleToGroup(form.grupos.length - 1);
};

const removeGroup = (index) => form.grupos.splice(index, 1);

const addRuleToGroup = (groupIndex) => {
    form.grupos[groupIndex].regras.push({
        tipo_regra: 'subtotal_carrinho',
        operador: 'maior_igual',
        valor: 0,
        valor_categoria: null,
        valor_produto: null
    });
};

const removeRuleFromGroup = (gIndex, rIndex) => form.grupos[gIndex].regras.splice(rIndex, 1);

const resetRuleValue = (rule) => {
    rule.valor = 0; 
    rule.valor_categoria = null; 
    rule.valor_produto = null;
    
    if (rule.tipo_regra === 'subtotal_carrinho' || rule.tipo_regra === 'total_itens_carrinho') {
        rule.operador = 'maior_igual';
    } else {
        rule.operador = 'contem';
    }
};

watch(() => props.offerToEdit, (newVal) => {
    if (newVal) {
        offerId.value = newVal.id;
        const acf = newVal.acf || {};
        
        form.produto_ofertado = acf.produto_ofertado || null;
        form.preco_promocional = Number(acf.preco_promocional);
        form.limite_total_usos = Number(acf.limite_total_usos);
        form.texto_da_oferta = acf.texto_da_oferta || '';
        form.oferta_status = acf.oferta_status;

        if (Array.isArray(acf.grupos_de_regras)) {
            form.grupos = acf.grupos_de_regras.map(g => ({
                regras: Array.isArray(g.regras) ? JSON.parse(JSON.stringify(g.regras)) : []
            }));
        } else {
            if (Array.isArray(acf.regras_de_ativacao) && acf.regras_de_ativacao.length > 0) {
                form.grupos = [{ regras: JSON.parse(JSON.stringify(acf.regras_de_ativacao)) }];
            } else {
                form.grupos = [];
            }
        }
    } else {
        offerId.value = null;
        form.produto_ofertado = null;
        form.preco_promocional = 0;
        form.limite_total_usos = 0;
        form.texto_da_oferta = '';
        form.oferta_status = true;
        form.grupos = [];
    }
}, { immediate: true });

const saveOffer = async () => {
    if (!form.produto_ofertado) { notify('warn', 'Erro', 'Selecione um produto.'); return; }
    
    saving.value = true;
    try {
        const payload = {
            title: form.texto_da_oferta || 'Oferta Auto',
            status: 'publish',
            fields: {
                produto_ofertado: form.produto_ofertado,
                preco_promocional: form.preco_promocional,
                limite_total_usos: form.limite_total_usos,
                texto_da_oferta: form.texto_da_oferta,
                oferta_status: form.oferta_status,
                grupos_de_regras: form.grupos.map(g => ({
                    regras: g.regras.map(r => ({
                        tipo_regra: r.tipo_regra,
                        operador: r.operador,
                        valor: (r.tipo_regra === 'subtotal_carrinho' || r.tipo_regra === 'total_itens_carrinho') ? r.valor : 1,
                        valor_categoria: r.valor_categoria,
                        valor_produto: r.valor_produto
                    }))
                }))
            }
        };

        const rootUrl = window.nativaData.root;
        const nonce = window.nativaData.nonce;
        const url = offerId.value 
            ? `${rootUrl}wp/v2/nativa_oferta/${offerId.value}` 
            : `${rootUrl}wp/v2/nativa_oferta`;

        const { data } = await axios.post(url, payload, {
            headers: { 'X-WP-Nonce': nonce }
        });
        
        notify('success', 'Salvo', 'Oferta atualizada!');
        emit('saved', data);
        isVisible.value = false;
    } catch (e) {
        console.error(e);
        notify('error', 'Erro', 'Falha ao salvar. Verifique permissões.');
    } finally {
        saving.value = false;
    }
};

watch(isVisible, (val) => {
    if (val && productsStore.products.length === 0) productsStore.fetchProducts();
    if (val && menuStore.categories.length === 0) menuStore.fetchMenu();
});
</script>