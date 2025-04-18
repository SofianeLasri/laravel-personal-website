import type { CreationType } from '@/types';

export const creationTypeLabels: Record<CreationType, string> = {
    portfolio: 'Portfolio',
    game: 'Jeu vidéo',
    library: 'Bibliothèque',
    website: 'Site web',
    tool: 'Outil',
    map: 'Map',
    other: 'Autre',
};

export const getTypeLabel = (type: CreationType): string => {
    return creationTypeLabels[type] || type;
};

export const creationTypes = Object.keys(creationTypeLabels) as CreationType[];
