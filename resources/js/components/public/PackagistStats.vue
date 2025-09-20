<script setup lang="ts">
import { useTranslation } from '@/composables/useTranslation';
import { Calendar, Code2, Download, Package, RefreshCw, Scale, Star, Tag, User, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface PackagistData {
    name: string;
    description: string | null;
    downloads: number;
    daily_downloads: number;
    monthly_downloads: number;
    stars: number;
    dependents: number;
    suggesters: number;
    type: string | null;
    repository: string | null;
    github_stars: number | null;
    github_watchers: number | null;
    github_forks: number | null;
    github_open_issues: number | null;
    language: string | null;
    license: string[] | null;
    latest_version: string | null;
    latest_stable_version: string | null;
    created_at: string | null;
    updated_at: string | null;
    url: string;
    maintainers: Array<{
        name: string;
        avatar_url: string | null;
    }>;
    php_version: string | null;
    laravel_version: string | null;
}

interface Props {
    packagistData: PackagistData | null;
}

const props = defineProps<Props>();
const { t } = useTranslation();

const formatNumber = (num: number): string => {
    if (num >= 1000000) {
        return `${(num / 1000000).toFixed(1)}M`;
    }
    if (num >= 1000) {
        return `${(num / 1000).toFixed(1)}K`;
    }
    return num.toString();
};

const formatDate = (dateString: string | null): string => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};

const formattedDownloads = computed(() => {
    if (!props.packagistData) return '0';
    return formatNumber(props.packagistData.downloads);
});

const formattedMonthlyDownloads = computed(() => {
    if (!props.packagistData) return '0';
    return formatNumber(props.packagistData.monthly_downloads);
});
computed(() => {
    if (!props.packagistData) return '0';
    return formatNumber(props.packagistData.daily_downloads);
});
</script>

<template>
    <div v-if="packagistData" class="packagist-stats">
        <div class="packagist-header">
            <h3 class="packagist-title">
                <Package class="icon" />
                {{ t('project.packagist_package') }}
            </h3>
            <a :href="packagistData.url" target="_blank" rel="noopener noreferrer" class="packagist-link"> {{ t('project.view_on_packagist') }} → </a>
        </div>

        <div class="package-name">
            <code>{{ packagistData.name }}</code>
            <span v-if="packagistData.latest_stable_version" class="version-badge">
                {{
                    packagistData.latest_stable_version.startsWith('v')
                        ? packagistData.latest_stable_version
                        : `v${packagistData.latest_stable_version}`
                }}
            </span>
        </div>

        <div v-if="packagistData.description" class="packagist-description">
            {{ packagistData.description }}
        </div>

        <div class="packagist-stats-grid">
            <div class="stat-item highlight">
                <Download class="stat-icon" />
                <div class="stat-content">
                    <div class="stat-value">{{ formattedDownloads }}</div>
                    <div class="stat-label">{{ t('project.total_downloads') }}</div>
                </div>
            </div>

            <div class="stat-item">
                <Download class="stat-icon smaller" />
                <div class="stat-content">
                    <div class="stat-value">{{ formattedMonthlyDownloads }}</div>
                    <div class="stat-label">{{ t('project.monthly_downloads') }}</div>
                </div>
            </div>

            <div class="stat-item">
                <Star class="stat-icon" />
                <div class="stat-content">
                    <div class="stat-value">{{ packagistData.stars }}</div>
                    <div class="stat-label">{{ t('project.favorites') }}</div>
                </div>
            </div>

            <div class="stat-item">
                <Package class="stat-icon" />
                <div class="stat-content">
                    <div class="stat-value">{{ packagistData.dependents }}</div>
                    <div class="stat-label">{{ t('project.dependents') }}</div>
                </div>
            </div>

            <div v-if="packagistData.suggesters > 0" class="stat-item">
                <Users class="stat-icon" />
                <div class="stat-content">
                    <div class="stat-value">{{ packagistData.suggesters }}</div>
                    <div class="stat-label">{{ t('project.suggesters') }}</div>
                </div>
            </div>

            <div v-if="packagistData.php_version" class="stat-item">
                <Code2 class="stat-icon" />
                <div class="stat-content">
                    <div class="stat-value">{{ packagistData.php_version }}</div>
                    <div class="stat-label">{{ t('project.php_required') }}</div>
                </div>
            </div>

            <div v-if="packagistData.laravel_version" class="stat-item">
                <Code2 class="stat-icon laravel" />
                <div class="stat-content">
                    <div class="stat-value">{{ packagistData.laravel_version }}</div>
                    <div class="stat-label">Laravel {{ t('project.version_required') }}</div>
                </div>
            </div>
        </div>

        <div class="packagist-meta">
            <div v-if="packagistData.type" class="meta-item">
                <Tag class="meta-icon" />
                <span>{{ packagistData.type }}</span>
            </div>

            <div v-if="packagistData.license && packagistData.license.length > 0" class="meta-item">
                <Scale class="meta-icon" />
                <span>{{ packagistData.license.join(', ') }}</span>
            </div>

            <div v-if="packagistData.created_at" class="meta-item">
                <Calendar class="meta-icon" />
                <span>{{ t('project.created') }} {{ formatDate(packagistData.created_at) }}</span>
            </div>

            <div v-if="packagistData.updated_at" class="meta-item">
                <RefreshCw class="meta-icon" />
                <span>{{ t('project.updated') }} {{ formatDate(packagistData.updated_at) }}</span>
            </div>
        </div>

        <div v-if="packagistData.maintainers && packagistData.maintainers.length > 0" class="maintainers-section">
            <h4 class="maintainers-title">{{ t('project.maintainers') }}</h4>
            <div class="maintainers-list">
                <div v-for="maintainer in packagistData.maintainers" :key="maintainer.name" class="maintainer">
                    <img v-if="maintainer.avatar_url" :src="maintainer.avatar_url" :alt="maintainer.name" class="maintainer-avatar" />
                    <User v-else class="maintainer-avatar-icon" />
                    <span class="maintainer-name">{{ maintainer.name }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.packagist-stats {
    background-color: #fff7ed;
    border-radius: 0.5rem;
    border: 1px solid #fed7aa;
    padding: 1.5rem;
}

.dark .packagist-stats {
    background-color: #1a0f08;
    border-color: #431407;
}

.packagist-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.packagist-title {
    font-size: 1.125rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.packagist-title .icon {
    width: 1.25rem;
    height: 1.25rem;
    color: #ea580c;
}

.dark .packagist-title .icon {
    color: #fb923c;
}

.packagist-link {
    font-size: 0.875rem;
    color: #ea580c;
    text-decoration: none;
    transition: color 0.2s;
}

.packagist-link:hover {
    text-decoration: underline;
    color: #dc2626;
}

.dark .packagist-link {
    color: #fb923c;
}

.dark .packagist-link:hover {
    color: #fed7aa;
}

.package-name {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.package-name code {
    font-family: 'Courier New', Courier, monospace;
    font-size: 1rem;
    font-weight: bold;
    color: #ea580c;
    background-color: #ffedd5;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
}

.dark .package-name code {
    color: #fb923c;
    background-color: #431407;
}

.version-badge {
    font-size: 0.875rem;
    font-weight: 500;
    color: #78716c;
    background-color: #fed7aa;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
}

.dark .version-badge {
    color: #d6d3d1;
    background-color: #7c2d12;
}

.packagist-description {
    color: #57534e;
    margin-bottom: 1rem;
}

.dark .packagist-description {
    color: #d6d3d1;
}

.packagist-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
}

@media (min-width: 768px) {
    .packagist-stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.stat-item.highlight .stat-icon {
    color: #dc2626;
}

.dark .stat-item.highlight .stat-icon {
    color: #fbbf24;
}

.stat-item.highlight .stat-value {
    color: #dc2626;
    font-weight: 700;
}

.dark .stat-item.highlight .stat-value {
    color: #fbbf24;
}

.stat-icon {
    width: 1.25rem;
    height: 1.25rem;
    color: #f97316;
}

.stat-icon.smaller {
    width: 1rem;
    height: 1rem;
}

.stat-icon.laravel {
    color: #f05340;
}

.dark .stat-icon {
    color: #fdba74;
}

.dark .stat-icon.laravel {
    color: #ff6b5a;
}

.stat-content {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 1.25rem;
    font-weight: 600;
}

.stat-label {
    font-size: 0.75rem;
    color: #78716c;
}

.dark .stat-label {
    color: #a8a29e;
}

.packagist-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    font-size: 0.875rem;
    color: #78716c;
    margin-top: 1rem;
}

.dark .packagist-meta {
    color: #a8a29e;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.meta-icon {
    width: 1rem;
    height: 1rem;
}

.maintainers-section {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #fed7aa;
}

.dark .maintainers-section {
    border-top-color: #431407;
}

.maintainers-title {
    font-size: 0.875rem;
    font-weight: 500;
    color: #78716c;
    margin-bottom: 0.75rem;
}

.dark .maintainers-title {
    color: #a8a29e;
}

.maintainers-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.maintainer {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.maintainer-avatar {
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 9999px;
    object-fit: cover;
}

.maintainer-avatar-icon {
    width: 1.5rem;
    height: 1.5rem;
    color: #78716c;
}

.dark .maintainer-avatar-icon {
    color: #a8a29e;
}

.maintainer-name {
    font-size: 0.875rem;
    font-weight: 500;
}

@media (max-width: 640px) {
    .packagist-stats {
        padding: 1rem;
    }
}
</style>
