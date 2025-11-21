# World Time AI – Default Prompts

Version: 1.0  
Formål: Dette dokument indeholder alle standard-prompts til OpenAI-generering. Alle prompts skal være redigerbare via admin-interface.

---

## Generelle instruktioner

Alle prompts modtager følgende variabler:

- `{location_name}` – Byens/landets/kontinentets navn (engelsk fra database)
- `{location_name_local}` – Oversat navn (hvis allerede tilgængeligt)
- `{country_name}` – Landets navn
- `{continent_name}` – Kontinentets navn
- `{timezone}` – IANA timezone (f.eks. "Europe/Copenhagen")
- `{base_language}` – Sproget indholdet skal skrives på
- `{base_language_description}` – Beskrivelse af sprogstil (f.eks. "Skriv på flydende dansk til danske brugere")
- `{base_country_name}` – Baselandet (f.eks. "Danmark")

---

## 1. Oversættelse af stednavne

**Prompt ID**: `translate_location_name`

**System Prompt**:
```
You are a professional translator specializing in geographical names and locations.
```

**User Prompt**:
```
Translate the following location name to {base_language}.

Location name: {location_name}
Location type: {location_type}
Country: {country_name}

Instructions:
- {base_language_description}
- Use the most common and natural translation for this location
- If the location name is already in the target language or is a proper noun that doesn't translate, return it unchanged
- Return ONLY the translated name, no explanations

Translated name:
```

---

## 2. By-side: Titel (post_title)

**Prompt ID**: `city_page_title`

**System Prompt**:
```
You are an SEO copywriter creating engaging page titles for a world time website.
```

**User Prompt**:
```
Create a natural, engaging page title for a webpage about the current time in a city.

City: {location_name_local}
Country: {country_name}
Timezone: {timezone}

Instructions:
- {base_language_description}
- The title should be engaging and SEO-friendly
- Target search intent: "what time is it in [city]?"
- Length: 40-60 characters
- Include the city name
- Make it natural and conversational
- Return ONLY the title, no explanations

Page title:
```

---

## 3. By-side: Indhold (post_content)

**Prompt ID**: `city_page_content`

**System Prompt**:
```
You are a professional content writer specializing in travel and world information.
```

**User Prompt**:
```
Write engaging, informative content for a webpage showing the current time in a city.

City: {location_name_local}
Country: {country_name}
Continent: {continent_name}
Timezone: {timezone}

Instructions:
- {base_language_description}
- Write 200-300 words
- Include information about:
  * Brief introduction to the city
  * Why people might need to know the time there
  * Timezone information (natural, not technical)
  * Time difference considerations (business hours, calling times, etc.)
- Use HTML formatting: <p>, <h2>, <h3>, <strong>, <em>
- Be informative but conversational
- SEO-optimized but natural
- Do NOT include the current time (that will be displayed separately)
- Return ONLY the HTML content, no explanations

Content:
```

---

## 4. Land-side: Titel (post_title)

**Prompt ID**: `country_page_title`

**System Prompt**:
```
You are an SEO copywriter creating engaging page titles for a world time website.
```

**User Prompt**:
```
Create a natural, engaging page title for a webpage about time zones and current time in a country.

Country: {location_name_local}
Continent: {continent_name}

Instructions:
- {base_language_description}
- The title should be engaging and SEO-friendly
- Target search intent: "what time is it in [country]?"
- Length: 40-60 characters
- Include the country name
- Make it natural and conversational
- Return ONLY the title, no explanations

Page title:
```

---

## 5. Land-side: Indhold (post_content)

**Prompt ID**: `country_page_content`

**System Prompt**:
```
You are a professional content writer specializing in travel and world information.
```

**User Prompt**:
```
Write engaging, informative content for a webpage showing time information for a country.

Country: {location_name_local}
Continent: {continent_name}

Instructions:
- {base_language_description}
- Write 300-400 words
- Include information about:
  * Brief introduction to the country
  * Timezone overview (if multiple timezones, mention that)
  * Why people might need to know the time there
  * Business and communication considerations
  * Cultural aspects related to time (if relevant)
- Use HTML formatting: <p>, <h2>, <h3>, <strong>, <em>
- Be informative but conversational
- SEO-optimized but natural
- Return ONLY the HTML content, no explanations

Content:
```

---

## 6. Kontinent-side: Titel (post_title)

**Prompt ID**: `continent_page_title`

**System Prompt**:
```
You are an SEO copywriter creating engaging page titles for a world time website.
```

**User Prompt**:
```
Create a natural, engaging page title for a webpage about time zones and current time across a continent.

Continent: {location_name_local}

Instructions:
- {base_language_description}
- The title should be engaging and SEO-friendly
- Target search intent: "what time is it in [continent]?"
- Length: 40-60 characters
- Include the continent name
- Make it natural and conversational
- Return ONLY the title, no explanations

Page title:
```

---

## 7. Kontinent-side: Indhold (post_content)

**Prompt ID**: `continent_page_content`

**System Prompt**:
```
You are a professional content writer specializing in travel and world information.
```

**User Prompt**:
```
Write engaging, informative content for a webpage showing time information for a continent.

Continent: {location_name_local}

Instructions:
- {base_language_description}
- Write 400-500 words
- Include information about:
  * Overview of the continent
  * Timezone diversity across the region
  * Major time zones in the continent
  * International business and travel considerations
  * Interesting facts about time zones in this region
- Use HTML formatting: <p>, <h2>, <h3>, <strong>, <em>
- Be informative but conversational
- SEO-optimized but natural
- Return ONLY the HTML content, no explanations

Content:
```

---

## 8. Yoast SEO: Title

**Prompt ID**: `yoast_seo_title`

**System Prompt**:
```
You are an SEO specialist creating optimized meta titles.
```

**User Prompt**:
```
Create an SEO-optimized meta title for a webpage about the current time in a location.

Location: {location_name_local}
Location type: {location_type}
Country: {country_name}

Instructions:
- {base_language_description}
- Length: 50-60 characters (strict limit for search engines)
- Include the location name
- Should target "what time is it in [location]" search intent
- Include relevant keywords naturally
- Make it compelling for click-through
- Return ONLY the meta title, no explanations

Meta title:
```

---

## 9. Yoast SEO: Meta Description

**Prompt ID**: `yoast_meta_description`

**System Prompt**:
```
You are an SEO specialist creating optimized meta descriptions.
```

**User Prompt**:
```
Create an SEO-optimized meta description for a webpage about the current time in a location.

Location: {location_name_local}
Location type: {location_type}
Country: {country_name}
Continent: {continent_name}

Instructions:
- {base_language_description}
- Length: 140-160 characters (strict limit for search engines)
- Include the location name
- Should target "what time is it in [location]" search intent
- Include a call-to-action or value proposition
- Make it compelling for click-through
- Be specific and informative
- Return ONLY the meta description, no explanations

Meta description:
```

---

## Prompt-variabler oversigt

Når prompts udføres, erstattes følgende variabler automatisk:

| Variabel | Beskrivelse | Eksempel |
|----------|-------------|----------|
| `{location_name}` | Original navn fra database (engelsk) | "Copenhagen" |
| `{location_name_local}` | Oversat navn | "København" |
| `{location_type}` | Type: "city", "country", "continent" | "city" |
| `{country_name}` | Landets navn | "Danmark" |
| `{continent_name}` | Kontinentets navn | "Europa" |
| `{timezone}` | IANA timezone | "Europe/Copenhagen" |
| `{base_language}` | Målsprog | "da-DK" |
| `{base_language_description}` | Sprogstil-instruktion | "Skriv på flydende dansk til danske brugere" |
| `{base_country_name}` | Baselandet | "Danmark" |

---

## Implementering i admin

Alle prompts skal:

1. Gemmes som WordPress options med prefix `wta_prompt_`
2. Være redigerbare via admin-interface (textarea felter)
3. Have reset-knap til standard-værdier
4. Vise tilgængelige variabler som hjælpetekst
5. Valideres for at sikre at nødvendige variabler er inkluderet
6. System prompt og User prompt skal gemmes separat

---

## Brug i koden

Eksempel på brug:

```php
$prompt_manager = new WTA_Prompt_Manager();

// Hent prompt med erstatninger
$user_prompt = $prompt_manager->get_prompt(
    'city_page_content',
    'user',
    [
        'location_name' => 'Berlin',
        'location_name_local' => 'Berlin',
        'country_name' => 'Tyskland',
        'continent_name' => 'Europa',
        'timezone' => 'Europe/Berlin',
        'base_language' => 'da-DK',
        'base_language_description' => 'Skriv på flydende dansk',
        'base_country_name' => 'Danmark',
    ]
);

// Send til OpenAI
$ai_service->generate($user_prompt);
```




