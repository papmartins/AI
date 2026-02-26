<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';
import { trans } from '@/Helpers/translation';

const props = defineProps({
    anomalies: {
        type: Array,
        default: () => []
    },
    statistics: {
        type: Object,
        default: () => ({})
    }
});

const loading = ref(false);
const showRetrainModal = ref(false);
const showUserModal = ref(false);
const selectedUserId = ref(null);
const userAnomalyResult = ref(null);
const userEmail = ref('');

const retrainModel = async () => {
    loading.value = true;
    showRetrainModal.value = false;
    
    try {
        const response = await axios.post('/api/anomaly/retrain');
        alert('✅ Model retrained successfully! Time: ' + response.data.training_time + 's');
        window.location.reload();
    } catch (error) {
        console.error('Retrain failed:', error);
        alert('❌ Retrain failed: ' + (error.response?.data?.message || error.message));
    } finally {
        loading.value = false;
    }
};

const checkSpecificUser = async () => {
    if (!userEmail.value) {
        alert('Please enter a user email');
        return;
    }
    
    loading.value = true;
    showUserModal.value = false;
    
    try {
        const response = await axios.get('/api/admin/users-by-email?email=' + userEmail.value);
        if (response.data.user) {
            selectedUserId.value = response.data.user.id;
            
            const anomalyResponse = await axios.get('/api/anomaly/users/' + selectedUserId.value);
            userAnomalyResult.value = anomalyResponse.data;
            showUserModal.value = true;
        } else {
            alert('User not found');
        }
    } catch (error) {
        console.error('User check failed:', error);
        alert('❌ Check failed: ' + (error.response?.data?.message || error.message));
    } finally {
        loading.value = false;
    }
};

const resolveAnomaly = async (anomalyId) => {
    if (confirm('Are you sure you want to mark this anomaly as resolved?')) {
        loading.value = true;
        try {
            await axios.post('/api/anomaly/resolve/' + anomalyId);
            alert('✅ Anomaly marked as resolved');
            window.location.reload();
        } catch (error) {
            console.error('Resolve failed:', error);
            alert('❌ Resolve failed: ' + (error.response?.data?.message || error.message));
        } finally {
            loading.value = false;
        }
    }
};

const getSeverityColor = (severity) => {
    const colors = {
        'high': 'bg-red-500',
        'medium': 'bg-yellow-500',
        'low': 'bg-blue-500',
        'none': 'bg-gray-500'
    };
    return colors[severity] || 'bg-gray-500';
};

const getSeverityText = (severity) => {
    const texts = {
        'high': 'High Risk',
        'medium': 'Medium Risk',
        'low': 'Low Risk',
        'none': 'No Risk'
    };
    return texts[severity] || 'Unknown';
};
</script>

<template>
    <Head :title="trans('Anomaly Detection Dashboard')" />
    
    <AuthenticatedLayout  :title="trans('Anomaly Detection Dashboard')">
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ trans('Total Anomalies') }}</div>
                        <div class="text-3xl font-bold text-gray-900 dark:text-white mt-2">
                            {{ statistics.total_anomalies || '0' }}
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ trans('Pending') }}</div>
                        <div class="text-3xl font-bold text-yellow-600 dark:text-yellow-400 mt-2">
                            {{ statistics.pending_anomalies || '0' }}
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ trans('Resolved') }}</div>
                        <div class="text-3xl font-bold text-green-600 dark:text-green-400 mt-2">
                            {{ statistics.resolved_anomalies || '0' }}
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ trans('Model Status') }}</div>
                        <div class="mt-2">
                            <span v-if="statistics.model_trained" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                ✅ Trained
                            </span>
                            <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                ❌ Not Trained
                            </span>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ statistics.last_trained || 'Never' }}
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-wrap gap-4 mb-8">
                    <PrimaryButton @click="showRetrainModal = true" :disabled="loading">
                        🔄 {{ trans('Retrain Model') }}
                    </PrimaryButton>
                    
                    <SecondaryButton @click="showUserModal = true">
                        🔍 {{ trans('Check Specific User') }}
                    </SecondaryButton>
                    
                    <PrimaryButton @click="window.location.reload()" :disabled="loading">
                        🔄 {{ trans('Refresh Data') }}
                    </PrimaryButton>
                </div>

                <!-- Anomaly Types Chart -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-8 p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        {{ trans('Anomaly Types Distribution') }}
                    </h3>
                    <div v-if="statistics.anomaly_types && statistics.anomaly_types.length" class="space-y-3">
                        <div v-for="type in statistics.anomaly_types" :key="type.type" class="flex items-center justify-between">
                            <div class="flex items-center">
                                <span class="w-3 h-3 rounded-full mr-3" :class="{
                                    'bg-red-500': type.type.includes('high'),
                                    'bg-yellow-500': type.type.includes('inconsistent'),
                                    'bg-blue-500': type.type.includes('late'),
                                    'bg-purple-500': type.type.includes('general')
                                }"></span>
                                <span class="text-gray-700 dark:text-gray-300 capitalize">
                                    {{ type.type.replace('_', ' ') }}
                                </span>
                            </div>
                            <div class="flex items-center">
                                <span class="text-gray-900 dark:text-white font-medium mr-2">
                                    {{ type.count }}
                                </span>
                                <span class="text-gray-500 dark:text-gray-400 text-sm">
                                    ({{ Math.round((type.count / statistics.total_anomalies) * 100) }}%)
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-gray-500 dark:text-gray-400">
                        {{ trans('No anomaly types found') }}
                    </div>
                </div>

                <!-- Anomalies Table -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h3 class="text-lg font-medium mb-4">
                            {{ trans('Detected Anomalies') }} ({{ anomalies.length }})
                        </h3>
                        
                        <div v-if="anomalies.length > 0" class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">{{ trans('User') }}</th>
                                        <th scope="col" class="px-6 py-3">{{ trans('Email') }}</th>
                                        <th scope="col" class="px-6 py-3">{{ trans('Score') }}</th>
                                        <th scope="col" class="px-6 py-3">{{ trans('Type') }}</th>
                                        <th scope="col" class="px-6 py-3">{{ trans('Severity') }}</th>
                                        <th scope="col" class="px-6 py-3">{{ trans('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="anomaly in anomalies" :key="anomaly.user_id" 
                                        class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                            {{ anomaly.user_name }}
                                        </td>
                                        <td class="px-6 py-4">
                                            {{ anomaly.user_email }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="font-mono">{{ anomaly.anomaly_score }}</span>
                                        </td>
                                        <td class="px-6 py-4 capitalize">
                                            {{ anomaly.anomaly_type.replace('_', ' ') }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                                  :class="getSeverityColor(anomaly.severity)">
                                                {{ getSeverityText(anomaly.severity) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <DangerButton @click="resolveAnomaly(anomaly.user_id)" size="sm">
                                                ✅ {{ trans('Resolve') }}
                                            </DangerButton>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="mt-2">{{ trans('No anomalies detected') }}</p>
                            <p class="text-sm mt-1">{{ trans('All users normal behavior') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Retrain Modal -->
        <Modal :show="showRetrainModal" @close="showRetrainModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                    🔄 {{ trans('Retrain Anomaly Detection Model') }}
                </h2>
                
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    {{ trans('Retrain model description') }}
                </p>
                
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    This process may take a few seconds depending on the number of users.
                </p>
                
                <div class="flex justify-end gap-4">
                    <SecondaryButton @click="showRetrainModal = false" :disabled="loading">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton @click="retrainModel" :disabled="loading" class="flex items-center">
                        <span v-if="!loading">Retrain Model</span>
                        <span v-else class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processing...
                        </span>
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- User Check Modal -->
        <Modal :show="showUserModal" @close="showUserModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                    🔍 {{ trans('Check Specific User') }}
                </h2>
                
                <div v-if="!userAnomalyResult">
                    <div class="mb-4">
                        <InputLabel for="userEmail" :value="trans('User Email')" />
                        <TextInput 
                            id="userEmail" 
                            type="email" 
                            class="mt-1 block w-full" 
                            v-model="userEmail" 
                            :placeholder="trans('User Email')"
                        />
                    </div>
                    
                    <div class="flex justify-end gap-4">
                        <SecondaryButton @click="showUserModal = false" :disabled="loading">
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton @click="checkSpecificUser" :disabled="loading">
                            Check User
                        </PrimaryButton>
                    </div>
                </div>
                
                <div v-else>
                    <h3 class="text-lg font-medium mb-4">
                        Analysis Results for {{ userAnomalyResult.user.name }}
                    </h3>
                    
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="text-sm text-gray-500 dark:text-gray-400">Anomaly Score</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ userAnomalyResult.current_analysis.score }}
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="text-sm text-gray-500 dark:text-gray-400">Severity</div>
                            <div class="text-2xl font-bold" :class="getSeverityColor(userAnomalyResult.current_analysis.severity).replace('bg-', 'text-')">
                                {{ getSeverityText(userAnomalyResult.current_analysis.severity) }}
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                            Detection Result
                        </div>
                        <div class="flex items-center gap-2">
                            <span v-if="userAnomalyResult.current_analysis.is_anomaly" 
                                  class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                ❌ Anomaly Detected
                            </span>
                            <span v-else 
                                  class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                ✅ Normal Behavior
                            </span>
                            
                            <span v-if="userAnomalyResult.current_analysis.is_anomaly" 
                                  class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                {{ userAnomalyResult.current_analysis.type.replace('_', ' ') }}
                            </span>
                        </div>
                    </div>
                    
                    <div v-if="userAnomalyResult.historical_anomalies && userAnomalyResult.historical_anomalies.length > 0" class="mb-6">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">
                            Historical Anomalies ({{ userAnomalyResult.historical_anomalies.length }})
                        </h4>
                        <div class="space-y-2 max-h-40 overflow-y-auto">
                            <div v-for="hist in userAnomalyResult.historical_anomalies" :key="hist.id" 
                                 class="flex items-center justify-between p-2 bg-white dark:bg-gray-800 rounded">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ new Date(hist.created_at).toLocaleDateString() }}
                                    </span>
                                    <span class="text-xs font-medium capitalize" :class="getSeverityColor(hist.severity).replace('bg-', 'text-')">
                                        {{ hist.type.replace('_', ' ') }}
                                    </span>
                                </div>
                                <span class="text-xs font-mono text-gray-500 dark:text-gray-400">
                                    Score: {{ hist.score }}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-4">
                        <SecondaryButton @click="userAnomalyResult = null; userEmail = ''">
                            Check Another User
                        </SecondaryButton>
                        <PrimaryButton @click="showUserModal = false">
                            Close
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Add any custom styles here */
</style>