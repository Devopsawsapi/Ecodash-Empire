<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * AIAnalysisService — Intelligence Artificielle EcoDash
 * Intègre Claude API pour analyses, prédictions, alertes et rapports narratifs
 *
 * Configuration requise dans .env :
 *   ANTHROPIC_API_KEY=sk-ant-...
 *   ANTHROPIC_MODEL=claude-sonnet-4-20250514
 */
class AIAnalysisService
{
    private string $apiUrl   = 'https://api.anthropic.com/v1/messages';
    private string $model;
    private string $apiKey;

    private string $systemPrompt = <<<'PROMPT'
Tu es EcoDash AI, expert en surveillance environnementale intégré au système EcoDash d'Empire Technova.
Tu analyses les données environnementales collectées sur le terrain (eau, air, sol, biodiversité, social/PGES)
et les compares avec les normes internationales OMS, FAO, ISO, IUCN, ILO.

Tes règles :
- Réponses toujours en JSON valide (sauf si rapport narratif explicitement demandé)
- Niveaux d'alerte : CONFORME, ATTENTION, ALERTE, CRITIQUE, URGENCE
- Contexte géographique : Afrique centrale / RDC (Congo)
- Précis, orienté action, avec recommandations concrètes
- Références aux normes : OMS/WHO 2021, FAO 2022, ISO 14001, ISO 15176, IUCN Red List, ILO
PROMPT;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.key');
        $this->model  = config('services.anthropic.model', 'claude-sonnet-4-20250514');
    }

    // ─────────────────────────────────────────────────────────────────
    // 1. ANALYSE COMPARATIVE — Données terrain vs normes internationales
    // ─────────────────────────────────────────────────────────────────
    public function analyserMesure(string $typeMesure, array $donneesTerrain, string $siteNom = '', ?array $donneesExternesRef = null): array
    {
        $prompt = sprintf(
            <<<PROMPT
Analyse ces données %s collectées sur le terrain au site "%s".

DONNÉES TERRAIN :
%s

DONNÉES RÉFÉRENCE INTERNATIONALES (si disponibles) :
%s

Retourne UNIQUEMENT ce JSON (pas de markdown, pas d'explication) :
{
  "resume_executif": "2-3 phrases état général",
  "score_qualite": 0,
  "niveau_alerte": "CONFORME|ATTENTION|ALERTE|CRITIQUE|URGENCE",
  "parametres": [
    {
      "nom": "paramètre",
      "valeur": 0,
      "unite": "unité",
      "norme_ref": "valeur norme OMS/FAO",
      "source_norme": "OMS 2021 / FAO 2022 / ISO...",
      "statut": "CONFORME|ATTENTION|ALERTE|CRITIQUE",
      "ecart_pct": 0,
      "risque_sante": "description risque",
      "recommandation": "action concrète"
    }
  ],
  "comparaison_regionale": "positionnement vs Afrique centrale",
  "causes_probables": ["cause 1"],
  "actions_urgentes": ["action 1"],
  "actions_preventives": ["action 1"],
  "prochaine_inspection": "délai recommandé"
}
PROMPT,
            strtoupper($typeMesure),
            $siteNom,
            json_encode($donneesTerrain, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            json_encode($donneesExternesRef ?? ['non_disponible' => true], JSON_UNESCAPED_UNICODE)
        );

        return $this->appeler($prompt);
    }

    // ─────────────────────────────────────────────────────────────────
    // 2. PRÉDICTION DE TENDANCES
    // ─────────────────────────────────────────────────────────────────
    public function predireTendances(string $typeMesure, array $historique, int $horizonJours = 30): array
    {
        $prompt = sprintf(
            <<<PROMPT
Analyse cet historique %s et prédit les tendances sur %d jours.

HISTORIQUE (chronologique) :
%s

Retourne UNIQUEMENT ce JSON :
{
  "tendance_generale": "HAUSSE|BAISSE|STABLE|VOLATILE",
  "fiabilite_pct": 0,
  "predictions": [
    {
      "parametre": "nom",
      "tendance": "HAUSSE|BAISSE|STABLE",
      "valeur_actuelle": 0,
      "valeur_predite_j7": 0,
      "valeur_predite_j30": 0,
      "probabilite_depassement_norme_pct": 0,
      "facteurs": ["facteur 1"],
      "alerte_precoce": false,
      "message_alerte": "si alerte_precoce true"
    }
  ],
  "scenario_optimiste": "description",
  "scenario_pessimiste": "description",
  "actions_preventives": ["action 1"]
}
PROMPT,
            strtoupper($typeMesure),
            $horizonJours,
            json_encode($historique, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        return $this->appeler($prompt);
    }

    // ─────────────────────────────────────────────────────────────────
    // 3. ALERTES INTELLIGENTES MULTI-MODULES
    // ─────────────────────────────────────────────────────────────────
    public function genererAlertes(array $toutesLesDonnees, array $historiqueAlertes = []): array
    {
        // Cache 15 min pour éviter appels répétitifs
        $cacheKey = 'ia_alertes_' . md5(json_encode($toutesLesDonnees));
        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($toutesLesDonnees, $historiqueAlertes) {
            $prompt = sprintf(
                <<<PROMPT
Analyse ces données multi-modules EcoDash et génère des alertes intelligentes prioritaires.

DONNÉES ACTUELLES :
%s

ALERTES RÉCENTES (pour éviter doublons) :
%s

Retourne UNIQUEMENT ce JSON :
{
  "niveau_risque_global": "FAIBLE|MODERE|ELEVE|CRITIQUE",
  "nb_alertes": 0,
  "alertes": [
    {
      "id": "ALT-001",
      "priorite": 1,
      "niveau": "INFO|ATTENTION|ALERTE|CRITIQUE|URGENCE",
      "module": "eau|air|sol|biodiversite|social",
      "titre": "titre court",
      "description": "description détaillée",
      "norme_depassee": "référence norme",
      "impact_potentiel": "impact santé/environnement",
      "action_immediate": "que faire maintenant",
      "responsable": "qui doit agir",
      "delai": "immédiat|24h|1 semaine|1 mois",
      "notifier_populations": false
    }
  ],
  "correlation_multi_polluants": "analyse des liens",
  "zone_impact": "description géographique"
}
PROMPT,
                json_encode($toutesLesDonnees, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                json_encode(array_slice($historiqueAlertes, -5), JSON_UNESCAPED_UNICODE)
            );

            return $this->appeler($prompt);
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // 4. RAPPORT NARRATIF (pour export PDF)
    // ─────────────────────────────────────────────────────────────────
    public function genererRapportNarratif(string $periode, array $donneesResume, string $siteNom = 'Tous les sites', string $typeRapport = 'mensuel'): string
    {
        $prompt = sprintf(
            <<<PROMPT
Génère un rapport environnemental %s professionnel.

PÉRIODE : %s
SITE(S) : %s
DONNÉES :
%s

Rédige un rapport structuré (texte, pas JSON) avec :
# RAPPORT ENVIRONNEMENTAL %s — %s
## Synthèse Exécutive
## 1. Qualité de l'Eau (normes OMS/ISO 5667)
## 2. Qualité de l'Air (normes WHO AQG 2021)
## 3. État des Sols (normes FAO/ISO 14256)
## 4. Biodiversité (IUCN/CBD)
## 5. Indicateurs Sociaux & PGES (ILO/ISO 26000)
## 6. Analyse des Tendances et Comparaison Internationale
## 7. Alertes et Incidents
## 8. Recommandations Prioritaires (classées par urgence)
## 9. Plan d'Action Proposé
## Conclusion
(Maximum 1500 mots, professionnel, orienté action)
PROMPT,
            strtoupper($typeRapport),
            $periode,
            $siteNom,
            json_encode($donneesResume, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            strtoupper($typeRapport),
            $periode
        );

        $result = $this->appeler($prompt, false);
        return $result['texte'] ?? '';
    }

    // ─────────────────────────────────────────────────────────────────
    // 5. CHATBOT IA ENVIRONNEMENTAL
    // ─────────────────────────────────────────────────────────────────
    public function chat(string $question, ?array $contexte = null, array $historiqueConversation = []): string
    {
        $messages = [];

        if ($contexte) {
            $messages[] = ['role' => 'user',      'content' => 'Contexte données EcoDash actuelles : ' . json_encode($contexte, JSON_UNESCAPED_UNICODE)];
            $messages[] = ['role' => 'assistant', 'content' => "J'ai pris en compte les données actuelles du dashboard EcoDash."];
        }

        // Ajouter les derniers échanges de l'historique (max 3)
        foreach (array_slice($historiqueConversation, -6) as $msg) {
            $messages[] = $msg;
        }

        $messages[] = ['role' => 'user', 'content' => $question];

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ])->timeout(30)->post($this->apiUrl, [
                'model'      => $this->model,
                'max_tokens' => 1000,
                'system'     => $this->systemPrompt . "\nRéponds de façon conversationnelle et concise. Pas de JSON.",
                'messages'   => $messages,
            ]);

            if ($response->successful()) {
                return $response->json('content.0.text') ?? 'Désolé, je n\'ai pas pu générer de réponse.';
            }

            Log::error('Claude API chat error: ' . $response->body());
            return 'Service IA temporairement indisponible. Veuillez réessayer.';

        } catch (\Exception $e) {
            Log::error('Claude API exception: ' . $e->getMessage());
            return 'Erreur de connexion au service IA.';
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Méthode privée — Appel API Claude
    // ─────────────────────────────────────────────────────────────────
    private function appeler(string $prompt, bool $jsonMode = true): array
    {
        if (!$this->apiKey) {
            return ['error' => 'ANTHROPIC_API_KEY non configurée dans .env'];
        }

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ])->timeout(45)->post($this->apiUrl, [
                'model'      => $this->model,
                'max_tokens' => 2000,
                'system'     => $this->systemPrompt,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

            if (!$response->successful()) {
                Log::error('Claude API error: ' . $response->status() . ' — ' . $response->body());
                return ['error' => 'API Claude indisponible', 'status' => $response->status()];
            }

            $text = $response->json('content.0.text') ?? '';

            if (!$jsonMode) {
                return ['texte' => $text];
            }

            // Nettoyer et parser JSON
            $clean = preg_replace('/^```json\s*/m', '', $text);
            $clean = preg_replace('/^```\s*/m', '', $clean);
            $clean = trim($clean);

            $parsed = json_decode($clean, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Claude JSON parse error: ' . json_last_error_msg() . ' | Response: ' . substr($clean, 0, 500));
                return ['raw' => $text, 'parse_error' => json_last_error_msg()];
            }

            return $parsed;

        } catch (\Exception $e) {
            Log::error('Claude API exception: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
