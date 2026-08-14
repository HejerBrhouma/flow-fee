import { Category } from '../models/category.model';

/**
 * Keyword → category-name matching, purely client-side (no external service — this session
 * has consistently avoided APIs that need an account/key when a free/local option covers the
 * need). Matched against the expense title as the user types, to suggest a category without
 * forcing them to pick one manually every time. Order matters where a merchant name could
 * plausibly fall into two categories (e.g. "Netflix" under both Divertissement and
 * Abonnements) — the first matching category in this list wins.
 */
const CATEGORY_KEYWORDS: Record<string, string[]> = {
  'Repas & Restaurant': [
    'restaurant', 'resto', 'café', 'cafe', 'brasserie', 'pizzeria', 'mcdo', "mcdonald",
    'kfc', 'starbucks', 'boulangerie', 'traiteur', 'déjeuner', 'dejeuner', 'diner', 'dîner',
    'burger', 'sandwich',
  ],
  'Transport': [
    'taxi', 'uber', 'bolt', 'essence', 'carburant', 'parking', 'train', 'sncf', 'metro',
    'métro', 'bus', 'péage', 'peage', 'autoroute', 'avion', 'vol ', 'billet',
  ],
  'Hébergement': ['hotel', 'hôtel', 'airbnb', 'booking', 'auberge', 'gite', 'gîte'],
  'Divertissement': [
    'cinéma', 'cinema', 'netflix', 'spotify', 'concert', 'théâtre', 'theatre', 'spectacle',
    'jeux', 'jeu vidéo',
  ],
  'Abonnements': ['abonnement', 'subscription', 'forfait mensuel'],
  'Téléphone & Internet': [
    'orange', 'sfr', 'bouygues', 'free mobile', 'internet', 'télécom', 'telecom', 'forfait',
    'ooredoo', 'tunisie telecom', 'topnet',
  ],
  'Formation': ['formation', 'cours', 'séminaire', 'seminaire', 'conférence', 'conference', 'udemy', 'coursera'],
  'Marketing & Pub': ['publicité', 'publicite', 'facebook ads', 'google ads', 'marketing', 'sponsoring'],
  'Fournitures bureau': ['papeterie', 'stylo', 'fourniture', 'cartouche', 'toner', 'ramette'],
  'Santé': ['pharmacie', 'médecin', 'medecin', 'docteur', 'hôpital', 'hopital', 'clinique', 'dentiste'],
  'Courses & Alimentation': [
    'carrefour', 'monoprix', 'auchan', 'leclerc', 'supermarché', 'supermarche', 'épicerie',
    'epicerie', 'casino', 'aziza', 'mg', 'géant', 'geant',
  ],
  'Matériel & Équipement': ['ordinateur', 'imprimante', 'matériel', 'materiel', 'équipement', 'equipement', 'fnac', 'darty'],
};

export function suggestCategory(title: string, categories: Category[]): Category | null {
  const normalized = title.trim().toLowerCase();
  if (!normalized) return null;

  for (const [categoryName, keywords] of Object.entries(CATEGORY_KEYWORDS)) {
    if (keywords.some(keyword => normalized.includes(keyword))) {
      const match = categories.find(c => c.name === categoryName);
      if (match) return match;
    }
  }

  return null;
}
