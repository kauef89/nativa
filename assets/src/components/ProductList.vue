<template>
  <div class="flex h-full w-full overflow-hidden">
    
    <div v-if="store.isLoading" class="flex items-center justify-center w-full h-full">
        <ProgressSpinner />
    </div>

    <div v-else class="flex w-full h-full">
      
      <div class="w-max flex-none bg-surface-900 border-r border-surface-800 overflow-y-auto flex flex-col">
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

      <div class="flex-1 bg-surface-950 overflow-y-auto p-6 scrollbar-thin">
        
        <div v-if="currentCategory" class="animate-fade-in mb-6">
            <div class="flex items-center text-white mb-2">
                <span class="w-1.5 h-8 bg-primary-500 rounded-full mr-3"></span>
                <span class="text-2xl font-bold">{{ currentCategory.name }}</span>
            </div>
            <div v-if="currentCategory.parent_id" class="text-sm text-surface-400 ml-4 flex items-center">
               <i class="pi pi-arrow-turn-down-right mr-2"></i> Em {{ getParentName(currentCategory.parent_id) }}
            </div>
        </div>

        <div v-if="currentCategory && currentCategory.products.length > 0" class="flex flex-col space-y-3 animate-fade-in">
            <div v-for="product in currentCategory.products" :key="product.id">
                
                <div 
                    class="group bg-surface-900 border border-surface-800 rounded-xl p-3 cursor-pointer hover:border-primary-500/50 hover:bg-surface-800 transition-all duration-200 flex items-center active:scale-[0.99]"
                    @click="emit('open-product', product)"
                >
                    <div class="w-16 h-16 rounded-lg bg-surface-800 flex-none overflow-hidden relative border border-surface-700">
                        <img v-if="product.image" :src="product.image" class="w-full h-full object-cover">
                        <div v-else class="flex items-center justify-center h-full text-surface-600">
                            <i class="pi fa-regular fa-image text-xl"></i>
                        </div>
                    </div>

                    <div class="flex-1 ml-4 min-w-0">
                        <div class="font-bold text-lg text-surface-100 group-hover:text-primary-400 transition-colors truncate">
                            {{ product.name }}
                        </div>
                        <div class="text-sm text-surface-400 truncate mt-0.5" v-if="product.description">
                            {{ product.description }}
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-4 ml-4">
                        <span class="text-primary-500 font-bold bg-primary-500/10 px-3 py-1.5 rounded-lg text-base whitespace-nowrap">
                            R$ {{ formatPrice(product.price) }}
                        </span>
                        <button class="w-10 h-10 rounded-full bg-surface-950 border border-surface-700 flex items-center justify-center text-surface-400 group-hover:bg-primary-500 group-hover:border-primary-500 group-hover:text-surface-900 transition-all shadow-sm">
                            <i class="pi fa-solid fa-plus text-sm font-bold"></i>
                        </button>
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