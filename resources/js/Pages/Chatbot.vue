<script setup>
import { ref, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePage } from '@inertiajs/vue3';
import { trans } from '@/Helpers/translation';

const props = defineProps({
    auth: Object,
});

const question = ref('');
const response = ref('');
const isLoading = ref(false);
const chatHistory = ref([]);

const $page = usePage();

const apiRequest = async (url, options = {}) => {
    const xsrfToken = decodeURIComponent(
        document.cookie
        .split('; ')
        .find(row => row.startsWith('XSRF-TOKEN='))?.split('=')[1] || ''
    );

    // Get current locale from page props
    const locale = $page.props.locale || 'en';

    const response = await fetch(url, {
        ...options,
        headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-Locale': locale,  // Send locale in headers
        ...(xsrfToken && { 'X-XSRF-TOKEN': xsrfToken }),  // ← SÓ ISTO
        ...options.headers,
        },
        credentials: 'include',
    });

    // Auto CSRF se falhar (419)
    if (response.status === 419) {
        await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
        return apiRequest(url, options);  // retry
    }

    return response;
};
const askQuestion = async () => {
    if (!question.value.trim()) return;

    isLoading.value = true;
    response.value = '';

    try {
        const response = await apiRequest('/api/nlp-chatbot/chat', {
            method: 'POST',
            body: JSON.stringify({ question: question.value })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        
        chatHistory.value.push({
            question: data.question,
            response: data.response,
            timestamp: new Date().toLocaleTimeString()
        });
        
        question.value = '';
    } catch (error) {
        response.value = trans('Sorry, an error occurred while processing your question.');
        console.error('Error:', error);
    } finally {
        isLoading.value = false;
    }
};

const examples = ref([]);
const isLoadingExamples = ref(true);

const askExemples = [
    'Movies starring Bruce Willis',
    'Movies directed by Christopher Nolan',
    'Filmes com a atriz Charlize Theron',
    'Filmes com Die Hard no título',
    'Recommend some popular movies',
    'Quem dirigiu Mad Max?',
    'Movies starring Will Ferrell',
    'Quais filmes estrelados por Ryan Gosling?',
    'Filmes com Love no título',
    'What movies should I watch?'
];

// Função para carregar exemplos com base no idioma
const loadExamplesForLanguage = async (language) => {
    try {
        isLoadingExamples.value = true;
        
        // Fetch suggestions from the API
        const response = await apiRequest('/api/nlp-chatbot/suggestions');
        
        if (response.ok) {
            const data = await response.json();
            examples.value = data.suggestions || [];
        } else {
            // Fallback to default examples if API fails
            examples.value = askExemples;
        }
    } catch (error) {
        console.error('Error loading examples:', error);
        // Fallback to default examples if API fails
        examples.value = askExemples;
    } finally {
        isLoadingExamples.value = false;
    }
};

// Load examples when component is mounted
onMounted(() => {
    loadExamplesForLanguage($page.props.locale || 'en');
});

const useExample = (example) => {
    question.value = example;
};

// Training functionality
const isTraining = ref(false);
const trainingStatus = ref('');

const trainModel = async () => {
    try {
        isTraining.value = true;
        trainingStatus.value = trans('Checking existing model...');
        
        // First check if model file exists
        const checkResponse = await apiRequest('/api/nlp-chatbot/check-model');
        
        if (checkResponse.ok) {
            const { exists } = await checkResponse.json();
            
            if (exists) {
                trainingStatus.value = trans('Existing model found. Deleting...');
                
                // Delete existing model
                const deleteResponse = await apiRequest('/api/nlp-chatbot/delete-model', {
                    method: 'DELETE'
                });
                
                if (!deleteResponse.ok) {
                    throw new Error(trans('Failed to delete existing model'));
                }
            }
            
            trainingStatus.value = trans('Training new model...');
            
            // Train new model
            const trainResponse = await apiRequest('/api/nlp-chatbot/train', {
                method: 'POST'
            });
            
            if (trainResponse.ok) {
                const result = await trainResponse.json();
                trainingStatus.value = trans('Training completed successfully! Model: ') + result.model_path;
                
                // Auto-clear status after 5 seconds
                setTimeout(() => {
                    trainingStatus.value = '';
                }, 5000);
            } else {
                throw new Error(trans('Training failed'));
            }
        } else {
            throw new Error(trans('Failed to check model status'));
        }
    } catch (error) {
        console.error('Training error:', error);
        trainingStatus.value = trans('Training error: ') + error.message;
        
        // Auto-clear error after 5 seconds
        setTimeout(() => {
            trainingStatus.value = '';
        }, 5000);
    } finally {
        isTraining.value = false;
    }
};
</script>

<template>
    <AuthenticatedLayout :title="trans('Movie Chatbot with NLP')">
        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="flex gap-2 mb-4">
                            <button 
                                @click="trainModel" 
                                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors" 
                                :disabled="isTraining"
                                :title="trans('Train NLP Model')"
                            >
                                <span v-if="!isTraining">🤖 {{ trans('Train') }}</span>
                                <span v-else class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ trans('Training...') }}
                                </span>
                            </button>
                        </div>
                        <!-- Training status display -->
                        <div v-if="trainingStatus" class="mb-4 p-2 bg-blue-50 dark:bg-blue-900/20 text-blue-800 dark:text-blue-200 text-sm rounded-lg">
                            {{ trainingStatus }}
                        </div>
                        <div class="mb-6">
                            <h3 class="text-lg font-medium mb-2">{{ trans('Ask about movies, actors or directors (with NLP):') }}</h3>
                            
                            <div class="flex flex-wrap gap-2 mb-4">
                                <template v-if="isLoadingExamples">
                                    <div class="flex items-center space-x-2 px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-sm rounded-full">
                                        <svg class="animate-spin h-4 w-4 text-blue-800 dark:text-blue-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>{{ trans('Loading examples...') }}</span>
                                    </div>
                                </template>
                                <template v-else>
                                    <button 
                                        v-for="example in examples" 
                                        :key="example" 
                                        @click="useExample(example)" 
                                        class="px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-sm rounded-full hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors"
                                    >
                                        "{{ example }}"
                                    </button>
                                </template>
                            </div>
                            
                            <div class="flex gap-2">
                                <input 
                                    v-model="question" 
                                    type="text" 
                                    :placeholder="trans('Ask a question about movies...')" 
                                    class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                    @keyup.enter="askQuestion"
                                    :disabled="isLoading"
                                >
                                
                                <button 
                                    @click="askQuestion" 
                                    class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors" 
                                    :disabled="isLoading || !question.trim()"
                                >
                                    <span v-if="!isLoading">{{ trans('Ask') }}</span>
                                    <span v-else class="flex items-center">
                                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        {{ trans('Thinking...') }}
                                    </span>
                                </button>
                                
                            </div>
                        </div>

                        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-lg font-medium mb-4">{{ trans('Conversation History:') }}</h3>
                            
                            <div v-if="chatHistory.length === 0" class="text-gray-500 dark:text-gray-400 text-center py-8">
                                {{ trans('Your conversation will appear here. Ask a question to start!') }}
                            </div>
                            
                            <div v-else class="space-y-6">
                                <div v-for="(item, index) in [...chatHistory].reverse()" :key="index" class="chat-message">
                                    <div class="flex justify-end mb-2">
                                        <div class="bg-blue-500 text-white p-3 rounded-lg max-w-[80%] rounded-br-none">
                                            <p class="font-medium">{{ item.question }}</p>
                                            <p class="text-xs opacity-75 mt-1">{{ item.timestamp }}</p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex justify-start">
                                        <div class="bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100 p-3 rounded-lg max-w-[80%] rounded-bl-none whitespace-pre-wrap" v-html="item.response"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.chat-message {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>