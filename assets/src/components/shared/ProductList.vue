<template>
  <div class="flex h-full w-full overflow-hidden">
    
    <div v-if="store.isLoading" class="flex items-center justify-center w-full h-full">
        <ProgressSpinner />
    </div>

    <div v-else class="flex w-full h-full">
      
      <div class="w-max flex-none bg-surface-900 border-r border-surface-800 overflow-y-auto flex flex-col scrollbar-thin">
        <div class="p-4 text-xs font-bold text-surface-400 uppercase tracking-wider whitespace-nowrap">Categorias</div>
        
        <ul class="list-none p-0 m-0 space-y-1 px-2 pb-4">
          <li v-for="cat in store.categories" :key="cat.id">
            
            <div 
                @click="handleParentClick(cat)"
                class="cursor-pointer py-3 px-3 rounded-lg transition-all duration-200 flex items-center justify-between group select-none whitespace-nowrap gap-4"
                :class="activeParent === cat.id ? 'bg-surface-800 text-white' : 'text-surface-300 hover:bg-surface-800/50 hover:text-surface-100'"
            >
                <span class="font-bold text-sm">{{ cat.name }}</span>
                
                <i v-if="cat.children && cat.children.length > 0" 
                   class="pi fa-solid fa-chevron-down text-xs transition-transform duration-300"
                   :class="expandedParents.includes(cat.id) ? 'rotate-180 text-primary-500' : ''"
                ></i>
            </div>

            <div v-if="cat.children && cat.children.length > 0 && expandedParents.includes(cat.id)" 
                 class="ml-3 pl-3 border-l border-surface-700 my-1 space-y-1 animate-fade-in"
            >
                <div 
                    v-if="cat.products.length > 0"
                    @click="selectCategory(cat)"
                    class="cursor-pointer py-2 px-3 rounded-md text-sm transition-colors flex items-center whitespace-nowrap"
                    :class="(activeTab === cat.id && !isChildActive) ? 'text-surface-900 font-bold bg-primary-500' : 'text-surface-400 hover:text-surface-200'"
                >
                    <i class="pi fa-solid fa-border-all text-xs mr-2 opacity-70"></i> Ver Todos
                </div>

                <div 
                    v-for="child in cat.children" 
                    :key="child.id"
                    @click="selectCategory(child, true)"
                    class="cursor-pointer py-2 px-3 rounded-md text-sm transition-colors whitespace-nowrap"
                    :class="activeTab === child.id ? 'text-surface-900 font-bold bg-primary-500' : 'text-surface-400 hover:text-surface-200'"
                >
                    {{ child.name }}
                </div>
            </div>

          </li>
        </ul>
      </div>

      <div class="flex-1 bg-surface-950 overflow-y-auto p-4 scrollbar-thin">
        
        <div v-if="currentCategory" class="animate-fade-in mb-4 sticky top-0 bg-surface-950 z-10 py-2 border-b border-surface-800">
            <div class="flex items-center text-white">
                <span class="w-1.5 h-6 bg-primary-500 rounded-full mr-3"></span>
                <span class="text-xl font-bold">{{ currentCategory.name }}</span>
            </div>
        </div>

        <div v-if="currentCategory && currentCategory.products.length > 0" class="grid grid-cols-1 xl:grid-cols-2 gap-3 animate-fade-in pb-10">
            <div v-for="product in currentCategory.products" :key="product.id">
                
                <div 
                    class="group bg-surface-900 border border-surface-800 rounded-xl p-2.5 cursor-pointer hover:border-primary-500/50 hover:bg-surface-800 transition-all duration-200 flex gap-3 active:scale-[0.98] h-20"
                    @click="emit('open-product', product)"
                >
                    <div class="w-14 h-14 rounded-lg bg-surface-800 flex-none overflow-hidden relative border border-surface-700 self-center">
                        <img v-if="product.image" :src="product.image" class="w-full h-full object-cover">
                        <div v-else class="flex items-center justify-center h-full text-surface-600">
                            <i class="pi fa-regular fa-image text-lg"></i>
                        </div>
                    </div>

                    <div class="flex-1 min-w-0 flex flex-col justify-between py-0.5">
                        <div class="font-bold text-sm text-surface-200 group-hover:text-white transition-colors truncate leading-tight">
                            {{ product.name }}
                        </div>
                        
                        <div class="flex items-center justify-between mt-1">
                            <span class="text-primary-400 font-bold font-mono text-sm">
                                R$ {{ formatPrice(product.price) }}
                            </span>
                            
                            <button class="w-7 h-7 rounded bg-surface-800 border border-surface-700 flex items-center justify-center text-surface-400 group-hover:bg-primary-500 group-hover:border-primary-500 group-hover:text-surface-900 transition-all shadow-sm">
                                <i class="pi fa-solid fa-plus text-xs font-bold"></i>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div v-else-if="currentCategory" class="flex flex-col items-center justify-center h-64 text-surface-500">
            <i class="pi pi-folder-open text-4xl mb-2 opacity-50"></i>
            <span>Nenhum produto nesta categoria.</span>
        </div>

      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useMenuStore } from '@/stores/menu-store';
import ProgressSpinner from 'primevue/progressspinner';

const store = useMenuStore();
const activeTab = ref(null); 
const activeParent = ref(null); 
const expandedParents = ref([]); 
const isChildActive = ref(false); 

const emit = defineEmits(['open-product']);

const formatPrice = (value) => {
  return parseFloat(value).toFixed(2).replace('.', ',');
};

const currentCategory = computed(() => {
    if (!activeTab.value) return null;
    const parent = store.categories.find(c => c.id === activeTab.value);
    if (parent) return parent;
    for (const p of store.categories) {
        if (p.children) {
            const child = p.children.find(c => c.id === activeTab.value);
            if (child) return child;
        }
    }
    return null;
});

const getParentName = (parentId) => {
    const parent = store.categories.find(c => c.id === parentId);
    return parent ? parent.name : '';
};

const handleParentClick = (cat) => {
    activeParent.value = cat.id;
    if (expandedParents.value.includes(cat.id)) {
        expandedParents.value = expandedParents.value.filter(id => id !== cat.id);
    } else {
        expandedParents.value.push(cat.id);
    }
    if (cat.products && cat.products.length > 0) {
        selectCategory(cat);
    } else if (cat.children && cat.children.length > 0) {
        // selectCategory(cat.children[0], true); 
    }
};

const selectCategory = (cat, isChild = false) => {
    activeTab.value = cat.id;
    isChildActive.value = isChild;
};

onMounted(async () => {
  await store.fetchMenu();
  if (store.categories.length > 0) {
    const first = store.categories[0];
    handleParentClick(first); 
    selectCategory(first);
  }
});
</script>