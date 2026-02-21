<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const props = defineProps({
    status: String,
    training_times: {
        type: Object,
        default: () => ({})
    }
});

const form = useForm({});

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
    fetch('/model-training/status')
        .then(response => response.json())
        .then(data => {
            trainingStatus.value = data;
        })
        .catch(error => {
            console.error('Error fetching training status:', error);
        });
};

const trainRecommendationModel = () => {
    isTraining.value = true;
    trainingProgress.value = 'Training recommendation model...';
    
    form.post('/model-training/train-recommendation', {
        onSuccess: () => {
            isTraining.value = false;
            fetchTrainingStatus();
        },
        onError: (errors) => {
            isTraining.value = false;
            trainingProgress.value = '';
        }
    });
};

const trainAnomalyModel = () => {
    isTraining.value = true;
    trainingProgress.value = 'Training anomaly detection model...';
    
    form.post('/model-training/train-anomaly', {
        onSuccess: () => {
            isTraining.value = false;
            fetchTrainingStatus();
        },
        onError: (errors) => {
            isTraining.value = false;
            trainingProgress.value = '';
        }
    });
};

const trainAllModels = () => {
    isTraining.value = true;
    trainingProgress.value = 'Training all models...';
    
    form.post('/model-training/train-all', {
        onSuccess: () => {
            isTraining.value = false;
            fetchTrainingStatus();
        },
        onError: (errors) => {
            isTraining.value = false;
            trainingProgress.value = '';
        }
    });
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
                Model Training Center
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        
                        <!-- Training Status -->
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Model Training Status</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Movie Recommendation Model -->
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-medium text-gray-800 mb-2">Movie Recommendation Model</h4>
                                    <div class="space-y-2 text-sm">
                                        <div>
                                            <span class="font-medium">Status:</span>
                                            <span :class="trainingStatus.movie_model.exists ? 'text-green-600' : 'text-red-600'">
                                                {{ trainingStatus.movie_model.exists ? 'Trained' : 'Not Trained' }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="font-medium">Size:</span>
                                            <span>{{ formatFileSize(trainingStatus.movie_model.size) }}</span>
                                        </div>
                                        <div>
                                            <span class="font-medium">Last Trained:</span>
                                            <span>{{ formatDate(trainingStatus.movie_model.last_modified) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Anomaly Detection Model -->
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-medium text-gray-800 mb-2">Anomaly Detection Model</h4>
                                    <div class="space-y-2 text-sm">
                                        <div>
                                            <span class="font-medium">Status:</span>
                                            <span :class="trainingStatus.anomaly_model.exists ? 'text-green-600' : 'text-red-600'">
                                                {{ trainingStatus.anomaly_model.exists ? 'Trained' : 'Not Trained' }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="font-medium">Size:</span>
                                            <span>{{ formatFileSize(trainingStatus.anomaly_model.size) }}</span>
                                        </div>
                                        <div>
                                            <span class="font-medium">Last Trained:</span>
                                            <span>{{ formatDate(trainingStatus.anomaly_model.last_modified) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Training Times Display -->
                        <div v-if="Object.keys(training_times).length > 0" class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Last Training Times</h3>
                            
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                    <div v-if="training_times.recommendation" class="flex justify-between">
                                        <span class="font-medium">Recommendation Model:</span>
                                        <span class="text-green-700 font-mono">{{ training_times.recommendation.toFixed(2) }} seconds</span>
                                    </div>
                                    <div v-if="training_times.anomaly" class="flex justify-between">
                                        <span class="font-medium">Anomaly Detection Model:</span>
                                        <span class="text-green-700 font-mono">{{ training_times.anomaly.toFixed(2) }} seconds</span>
                                    </div>
                                    <div v-if="training_times.movie" class="flex justify-between">
                                        <span class="font-medium">Movie Model:</span>
                                        <span class="text-green-700 font-mono">{{ training_times.movie.toFixed(2) }} seconds</span>
                                    </div>
                                    <div v-if="training_times.total" class="flex justify-between">
                                        <span class="font-medium">Total Training Time:</span>
                                        <span class="text-green-700 font-mono font-bold">{{ training_times.total.toFixed(2) }} seconds</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Training Controls -->
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Training Controls</h3>
                            
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
                                        Train Recommendation Model
                                    </button>

                                    <button 
                                        @click="trainAnomalyModel"
                                        :disabled="isTraining"
                                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        Train Anomaly Detection Model
                                    </button>

                                    <button 
                                        @click="trainAllModels"
                                        :disabled="isTraining"
                                        class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        Train All Models
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Training Information -->
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Training Information</h3>
                            
                            <div class="bg-yellow-50 p-4 rounded-lg">
                                <h4 class="font-medium text-gray-800 mb-2">Important Notes:</h4>
                                <ul class="list-disc list-inside space-y-1 text-sm">
                                    <li>Training may take some time depending on the dataset size</li>
                                    <li>Models are automatically saved and will be used for future predictions</li>
                                    <li>You can train individual models or all models at once</li>
                                    <li>Training status is updated in real-time</li>
                                    <li>Existing models will be overwritten when retraining</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Model Performance -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Model Performance</h3>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-600">
                                    Model performance metrics will be displayed here after training.
                                    This includes accuracy, precision, recall, and other relevant metrics
                                    based on the validation dataset.
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