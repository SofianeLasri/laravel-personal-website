<script setup lang="ts">
import { useTranslation } from '@/composables/useTranslation';
import { AlertCircle, Calendar, Code, Eye, GitBranch, GitFork, RefreshCw, Scale, Star } from 'lucide-vue-next';
import { computed } from 'vue';

interface GitHubData {
    name: string;
    description: string | null;
    stars: number;
    forks: number;
    watchers: number;
    language: string | null;
    topics: string[];
    license: string | null;
    updated_at: string;
    created_at: string;
    open_issues: number;
    default_branch: string;
    size: number;
    url: string;
    homepage: string | null;
}

interface GitHubLanguages {
    [key: string]: number;
}

interface Props {
    githubData: GitHubData | null;
    githubLanguages: GitHubLanguages | null;
}

const props = defineProps<Props>();
const { t } = useTranslation();

const formatDate = (dateString: string): string => {
    const date = new Date(dateString);
    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};
const topLanguages = computed(() => {
    if (!props.githubLanguages) return [];
    return Object.entries(props.githubLanguages)
        .slice(0, 4)
        .map(([lang, percentage]) => ({ lang, percentage }));
});

const languageColors: Record<string, string> = {
    JavaScript: '#f1e05a',
    TypeScript: '#3178c6',
    Python: '#3572A5',
    Java: '#b07219',
    PHP: '#4F5D95',
    Ruby: '#701516',
    Go: '#00ADD8',
    Rust: '#dea584',
    'C++': '#f34b7d',
    'C#': '#178600',
    Swift: '#FA7343',
    Kotlin: '#A97BFF',
    Dart: '#00B4AB',
    Vue: '#41b883',
    HTML: '#e34c26',
    CSS: '#563d7c',
    SCSS: '#c6538c',
    Shell: '#89e051',
    Dockerfile: '#384d54',
};

const getLanguageColor = (language: string): string => {
    return languageColors[language] || '#8b949e';
};
</script>

<template>
    <div v-if="githubData" class="github-stats">
        <div class="github-header">
            <h3 class="github-title">
                <Code class="icon" />
                {{ t('project.github_repository') }}
            </h3>
            <a :href="githubData.url" target="_blank" rel="noopener noreferrer" class="github-link"> {{ t('project.view_on_github') }} → </a>
        </div>

        <div v-if="githubData.description" class="github-description">
            {{ githubData.description }}
        </div>

        <div class="github-stats-grid">
            <div class="stat-item">
                <Star class="stat-icon" />
                <div class="stat-content">
                    <div class="stat-value">{{ githubData.stars }}</div>
                    <div class="stat-label">{{ t('project.stars') }}</div>
                </div>
            </div>

            <div class="stat-item">
                <GitFork class="stat-icon" />
                <div class="stat-content">
                    <div class="stat-value">{{ githubData.forks }}</div>
                    <div class="stat-label">{{ t('project.forks') }}</div>
                </div>
            </div>

            <div class="stat-item">
                <Eye class="stat-icon" />
                <div class="stat-content">
                    <div class="stat-value">{{ githubData.watchers }}</div>
                    <div class="stat-label">{{ t('project.watchers') }}</div>
                </div>
            </div>

            <div class="stat-item">
                <AlertCircle class="stat-icon" />
                <div class="stat-content">
                    <div class="stat-value">{{ githubData.open_issues }}</div>
                    <div class="stat-label">{{ t('project.issues') }}</div>
                </div>
            </div>
        </div>

        <div v-if="githubLanguages && topLanguages.length > 0" class="languages-section">
            <h4 class="languages-title">{{ t('project.languages') }}</h4>
            <div class="languages-bar">
                <div
                    v-for="{ lang, percentage } in topLanguages"
                    :key="lang"
                    class="language-segment"
                    :style="{
                        width: `${percentage}%`,
                        backgroundColor: getLanguageColor(lang),
                    }"
                    :title="`${lang}: ${percentage}%`"
                />
            </div>
            <div class="languages-list">
                <div v-for="{ lang, percentage } in topLanguages" :key="lang" class="language-item">
                    <span class="language-dot" :style="{ backgroundColor: getLanguageColor(lang) }" />
                    <span class="language-name">{{ lang }}</span>
                    <span class="language-percentage">{{ percentage }}%</span>
                </div>
            </div>
        </div>

        <div class="github-meta">
            <div v-if="githubData.license" class="meta-item">
                <Scale class="meta-icon" />
                <span>{{ githubData.license }}</span>
            </div>

            <div class="meta-item">
                <GitBranch class="meta-icon" />
                <span>{{ githubData.default_branch }}</span>
            </div>

            <div class="meta-item">
                <Calendar class="meta-icon" />
                <span>{{ t('project.created') }} {{ formatDate(githubData.created_at) }}</span>
            </div>

            <div class="meta-item">
                <RefreshCw class="meta-icon" />
                <span>{{ t('project.updated') }} {{ formatDate(githubData.updated_at) }}</span>
            </div>
        </div>

        <div v-if="githubData.topics && githubData.topics.length > 0" class="topics-section">
            <div class="topics-list">
                <span v-for="topic in githubData.topics" :key="topic" class="topic-badge">
                    {{ topic }}
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.github-stats {
    background-color: #fff7ed;
    border-radius: 0.5rem;
    border: 1px solid #fed7aa;
    padding: 1.5rem;
}

.dark .github-stats {
    background-color: #1a0f08;
    border-color: #431407;
}

.github-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.github-title {
    font-size: 1.125rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.github-title .icon {
    width: 1.25rem;
    height: 1.25rem;
    color: #ea580c;
}

.dark .github-title .icon {
    color: #fb923c;
}

.github-link {
    font-size: 0.875rem;
    color: #ea580c;
    text-decoration: none;
    transition: color 0.2s;
}

.github-link:hover {
    text-decoration: underline;
    color: #dc2626;
}

.dark .github-link {
    color: #fb923c;
}

.dark .github-link:hover {
    color: #fed7aa;
}

.github-description {
    color: #57534e;
    margin-bottom: 1rem;
}

.dark .github-description {
    color: #d6d3d1;
}

.github-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
}

@media (min-width: 768px) {
    .github-stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.stat-icon {
    width: 1.25rem;
    height: 1.25rem;
    color: #f97316;
}

.dark .stat-icon {
    color: #fdba74;
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

.languages-section {
    margin-top: 1rem;
}

.languages-title {
    font-size: 0.875rem;
    font-weight: 500;
    color: #78716c;
    margin-bottom: 0.75rem;
}

.dark .languages-title {
    color: #a8a29e;
}

.languages-bar {
    display: flex;
    height: 0.5rem;
    border-radius: 9999px;
    overflow: hidden;
    background-color: #fed7aa;
    margin-bottom: 0.75rem;
}

.dark .languages-bar {
    background-color: #431407;
}

.language-segment {
    transition: opacity 0.3s;
}

.language-segment:hover {
    opacity: 0.8;
}

.languages-list {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}

.language-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
}

.language-dot {
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 9999px;
}

.language-name {
    font-weight: 500;
}

.language-percentage {
    color: #78716c;
}

.dark .language-percentage {
    color: #a8a29e;
}

.github-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    font-size: 0.875rem;
    color: #78716c;
    margin-top: 1rem;
}

.dark .github-meta {
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

.topics-section {
    padding-top: 0.5rem;
}

.topics-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.topic-badge {
    padding: 0.25rem 0.625rem;
    font-size: 0.75rem;
    font-weight: 500;
    background-color: #ffedd5;
    color: #c2410c;
    border-radius: 9999px;
}

.dark .topic-badge {
    background-color: #431407;
    color: #fdba74;
}

@media (max-width: 640px) {
    .github-stats {
        padding: 1rem;
    }
}
</style>
