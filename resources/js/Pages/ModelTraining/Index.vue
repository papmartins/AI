<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { trans } from '@/Helpers/translation';

const props = defineProps({
    status: String,
    training_times: {
        type: Object,
        default: () => ({})
    }
});

const apiRequest = async (url, options = {}) => {
  const xsrfToken = decodeURIComponent(
    document.cookie
      .split('; ')
      .find(row => row.startsWith('XSRF-TOKEN='))?.split('=')[1] || ''
  );

  const response = await fetch(url, {
    ...options,
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
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

const trainingStatus = ref({
    movie_model: { exists: false, size: 0, last_modified: null },
    anomaly_model: { exists: false, size: 0, last_modified: null }
});

const isTraining = ref(false);
const trainingProgress = ref('');

// Fetch training status on component mount
onMounted(() => {
    fetchTrainingStatus();
});

const fetchTrainingStatus = () => {
    apiRequest('/api/model-training/status', {
        method: 'GET'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        // Ensure data has the expected structure
        if (data && typeof data === 'object') {
            trainingStatus.value = {
                movie_model: data.movie_model || { exists: false, size: 0, last_modified: null },
                anomaly_model: data.anomaly_model || { exists: false, size: 0, last_modified: null }
            };
        }
    })
    .catch(error => {
        console.error('Error fetching training status:', error);
        // Set default values on error
        trainingStatus.value = {
            movie_model: { exists: false, size: 0, last_modified: null },
            anomaly_model: { exists: false, size: 0, last_modified: null }
        };
    });
};

const trainRecommendationModel = async () => {
    isTraining.value = true;
    trainingProgress.value = trans('Training recommendation model...');
    
    try {
        const response = await apiRequest('/api/model-training/train-recommendation', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            // Show success message
            if (typeof window !== 'undefined' && window.Inertia) {
                window.Inertia.visit(window.location.href, {
                    method: 'get',
                    data: {
                        status: 'success',
                        recommendation_training_time: data.training_time
                    },
                    preserveState: true,
                    preserveScroll: true
                });
            }
        } else {
            throw new Error(data.message || 'Training failed');
        }
    } catch (error) {
        console.error('Training error:', error);
        if (typeof window !== 'undefined' && window.Inertia) {
            window.Inertia.visit(window.location.href, {
                method: 'get',
                data: { 
                    status: 'error',
                    error: error.message 
                },
                preserveState: true,
                preserveScroll: true
            });
        }
    } finally {
        isTraining.value = false;
        fetchTrainingStatus();
    }
};

const trainAnomalyModel = async () => {
    isTraining.value = true;
    trainingProgress.value = trans('Training anomaly detection model...');
    
    try {
        const response = await apiRequest('/api/model-training/train-anomaly', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            // Show success message
            if (typeof window !== 'undefined' && window.Inertia) {
                window.Inertia.visit(window.location.href, {
                    method: 'get',
                    data: {
                        status: 'success',
                        anomaly_training_time: data.training_time
                    },
                    preserveState: true,
                    preserveScroll: true
                });
            }
        } else {
            throw new Error(data.message || 'Training failed');
        }
    } catch (error) {
        console.error('Training error:', error);
        if (typeof window !== 'undefined' && window.Inertia) {
            window.Inertia.visit(window.location.href, {
                method: 'get',
                data: { status: 'error' },
                preserveState: true,
                preserveScroll: true
            });
        }
    } finally {
        isTraining.value = false;
        fetchTrainingStatus();
    }
};

const trainAllModels = async () => {
    isTraining.value = true;
    trainingProgress.value = trans('Training all models...');
    
    try {
        const response = await apiRequest('/api/model-training/train-all', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            // Show success message with all training times
            if (typeof window !== 'undefined' && window.Inertia) {
                window.Inertia.visit(window.location.href, {
                    method: 'get',
                    data: {
                        status: 'success',
                        movie_training_time: data.training_times.movie,
                        anomaly_training_time: data.training_times.anomaly,
                        total_training_time: data.training_times.total
                    },
                    preserveState: true,
                    preserveScroll: true
                });
            }
        } else {
            throw new Error(data.message || 'Training failed');
        }
    } catch (error) {
        console.error('Training error:', error);
        if (typeof window !== 'undefined' && window.Inertia) {
            window.Inertia.visit(window.location.href, {
                method: 'get',
                data: { status: 'error' },
                preserveState: true,
                preserveScroll: true
            });
        }
    } finally {
        isTraining.value = false;
        fetchTrainingStatus();
    }
};

const formatDate = (timestamp) => {
    if (!timestamp) return 'Never';
    return new Date(timestamp * 1000).toLocaleString();
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 KB';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};
</script>

<template>
    <Head title="Model Training" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ trans('Model Training Center') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        
                        <!-- Training Status -->
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ trans('Model Training Status') }}</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Movie Recommendation Model -->
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-medium text-gray-800 mb-2">{{ trans('Movie Recommendation Model') }}</h4>
                                    <div class="space-y-2 text-sm">
                                        <div>
                                            <span class="font-medium">{{ trans('Status:') }}</span>
                                            <span :class="trainingStatus.movie_model.exists ? 'text-green-600' : 'text-red-600'">
                                                {{ trainingStatus.movie_model.exists ? trans('Trained') : trans('Not Trained') }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="font-medium">{{ trans('Size:') }}</span>
                                            <span>{{ formatFileSize(trainingStatus.movie_model.size) }}</span>
                                        </div>
                                        <div>
                                            <span class="font-medium">{{ trans('Last Trained:') }}</span>
                                            <span>{{ formatDate(trainingStatus.movie_model.last_modified) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Anomaly Detection Model -->
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-medium text-gray-800 mb-2">{{ trans('Anomaly Detection Model') }}</h4>
                                    <div class="space-y-2 text-sm">
                                        <div>
                                            <span class="font-medium">{{ trans('Status:') }}</span>
                                            <span :class="trainingStatus.anomaly_model.exists ? 'text-green-600' : 'text-red-600'">
                                                {{ trainingStatus.anomaly_model.exists ? trans('Trained') : trans('Not Trained') }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="font-medium">{{ trans('Size:') }}</span>
                                            <span>{{ formatFileSize(trainingStatus.anomaly_model.size) }}</span>
                                        </div>
                                        <div>
                                            <span class="font-medium">{{ trans('Last Trained:') }}</span>
                                            <span>{{ formatDate(trainingStatus.anomaly_model.last_modified) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Training Times Display -->
                        <div v-if="Object.keys(training_times).length > 0" class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ trans('Last Training Times') }}</h3>
                            
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                    <div v-if="training_times.recommendation" class="flex justify-between">
                                        <span class="font-medium">{{ trans('Recommendation Model:') }}</span>
                                        <span class="text-green-700 font-mono">{{ training_times.recommendation.toFixed(2) }} {{ trans('seconds') }}</span>
                                    </div>
                                    <div v-if="training_times.anomaly" class="flex justify-between">
                                        <span class="font-medium">{{ trans('Anomaly Detection Model:') }}</span>
                                        <span class="text-green-700 font-mono">{{ training_times.anomaly.toFixed(2) }} {{ trans('seconds') }}</span>
                                    </div>
                                    <div v-if="training_times.movie" class="flex justify-between">
                                        <span class="font-medium">{{ trans('Movie Model:') }}</span>
                                        <span class="text-green-700 font-mono">{{ training_times.movie.toFixed(2) }} {{ trans('seconds') }}</span>
                                    </div>
                                    <div v-if="training_times.total" class="flex justify-between">
                                        <span class="font-medium">{{ trans('Total Training Time:') }}</span>
                                        <span class="text-green-700 font-mono font-bold">{{ training_times.total.toFixed(2) }} {{ trans('seconds') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Training Controls -->
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ trans('Training Controls') }}</h3>
                            
                            <div class="space-y-4">
                                <!-- Training progress indicator -->
                                <div v-if="isTraining" class="bg-blue-50 p-4 rounded-lg">
                                    <div class="flex items-center space-x-2">
                                        <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="text-blue-700 font-medium">{{ trainingProgress }}</span>
                                    </div>
                                </div>

                                <!-- Training buttons -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <button 
                                        @click="trainRecommendationModel"
                                        :disabled="isTraining"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {{ trans('Train Recommendation Model') }}
                                    </button>

                                    <button 
                                        @click="trainAnomalyModel"
                                        :disabled="isTraining"
                                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {{ trans('Train Anomaly Detection Model') }}
                                    </button>

                                    <button 
                                        @click="trainAllModels"
                                        :disabled="isTraining"
                                        class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {{ trans('Train All Models') }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Training Information -->
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ trans('Training Information') }}</h3>
                            
                            <div class="bg-yellow-50 p-4 rounded-lg">
                                <h4 class="font-medium text-gray-800 mb-2">{{ trans('Important Notes:') }}</h4>
                                <ul class="list-disc list-inside space-y-1 text-sm">
                                    <li>{{ trans('Training may take some time depending on the dataset size') }}</li>
                                    <li>{{ trans('Models are automatically saved and will be used for future predictions') }}</li>
                                    <li>{{ trans('You can train individual models or all models at once') }}</li>
                                    <li>{{ trans('Training status is updated in real-time') }}</li>
                                    <li>{{ trans('Existing models will be overwritten when retraining') }}</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Model Performance -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ trans('Model Performance') }}</h3>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-600">
                                    {{ trans('Model performance metrics will be displayed here after training.') }}
                                    {{ trans('This includes accuracy, precision, recall, and other relevant metrics') }}
                                    {{ trans('based on the validation dataset.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Add any component-specific styles here */
</style>