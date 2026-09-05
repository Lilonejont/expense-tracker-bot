<?php
// categorizer.php - Automatic expense categorization engine

function getDefaultCategories() {
    return [
        [
            'id' => 1,
            'name' => 'Еда',
            'emoji' => '🍔',
            'keywords' => [
                'кофе', 'обед', 'ужин', 'завтрак', 'суши', 'пицца', 'бургер',
                'продукты', 'магазин', 'супермаркет', 'кафе', 'ресторан',
                'шаурма', 'доставка', 'еда', 'перекус', 'ланч', 'столовая',
                'пятёрочка', 'пятерочка', 'ашан', 'лента', 'дикси', 'вкусвилл',
                'яндекс еда', 'самокат', 'мясо', 'хлеб', 'молоко', 'фрукты',
                'овощи', 'чай', 'вода', 'сок', 'пиво', 'вино', 'бар'
            ]
        ],
        [
            'id' => 2,
            'name' => 'Транспорт',
            'emoji' => '🚕',
            'keywords' => [
                'такси', 'метро', 'автобус', 'бензин', 'каршеринг', 'парковка',
                'убер', 'uber', 'яндекс такси', 'трамвай', 'электричка',
                'поезд', 'ржд', 'самолёт', 'самолет', 'билет', 'проезд',
                'сапсан', 'аэроэкспресс', 'заправка'
            ]
        ],
        [
            'id' => 3,
            'name' => 'Дом',
            'emoji' => '🏠',
            'keywords' => [
                'аренда', 'квартира', 'коммуналка', 'квартплата', 'ремонт', 'мебель',
                'ипотека', 'уборка', 'химчистка', 'сантехник', 'электрик',
                'жкх', 'свет', 'газ', 'посуда', 'отопление'
            ]
        ],
        [
            'id' => 4,
            'name' => 'Развлечения',
            'emoji' => '🎮',
            'keywords' => [
                'кино', 'концерт', 'игра', 'netflix', 'spotify', 'театр',
                'музей', 'клуб', 'вечеринка', 'боулинг', 'подписка',
                'youtube', 'steam', 'playstation', 'кинотеатр'
            ]
        ],
        [
            'id' => 5,
            'name' => 'Одежда',
            'emoji' => '👕',
            'keywords' => [
                'одежда', 'обувь', 'куртка', 'штаны', 'футболка', 'джинсы',
                'кроссовки', 'платье', 'рубашка', 'костюм', 'шапка',
                'носки', 'сумка', 'zara', 'wildberries', 'ozon'
            ]
        ],
        [
            'id' => 6,
            'name' => 'Здоровье',
            'emoji' => '💊',
            'keywords' => [
                'аптека', 'врач', 'стоматолог', 'анализы', 'лекарства',
                'больница', 'поликлиника', 'массаж', 'витамины', 'медицина',
                'зубной', 'окулист', 'терапевт'
            ]
        ],
        [
            'id' => 7,
            'name' => 'Связь',
            'emoji' => '📱',
            'keywords' => [
                'телефон', 'мобильный', 'связь', 'интернет', 'wifi', 'вайфай',
                'мтс', 'билайн', 'мегафон', 'теле2', 'симка', 'тариф', 'баланс', 'vpn'
            ]
        ],
        [
            'id' => 8,
            'name' => 'Образование',
            'emoji' => '🎓',
            'keywords' => [
                'книга', 'курс', 'обучение', 'учёба', 'учеба', 'репетитор',
                'школа', 'университет', 'лекция', 'тренинг'
            ]
        ],
        [
            'id' => 9,
            'name' => 'Работа',
            'emoji' => '💼',
            'keywords' => [
                'работа', 'офис', 'канцелярия', 'хостинг', 'сервер',
                'домен', 'реклама', 'бизнес', 'налог', 'налоги'
            ]
        ],
        [
            'id' => 10,
            'name' => 'Красота',
            'emoji' => '💅',
            'keywords' => [
                'стрижка', 'парикмахер', 'маникюр', 'барбер', 'барбершоп',
                'салон', 'косметика', 'крем', 'шампунь', 'духи'
            ]
        ],
        [
            'id' => 11,
            'name' => 'Другое',
            'emoji' => '❓',
            'keywords' => []
        ]
    ];
}

/**
 * Determine category based on description and explicit tag.
 *
 * @param string $description
 * @param array $categories
 * @param string|null $explicitTag
 * @return array
 */
function categorizeExpense($description, $categories, $explicitTag = null) {
    $fallback = null;
    foreach ($categories as $cat) {
        if ($cat['name'] === 'Другое') {
            $fallback = $cat;
            break;
        }
    }
    if (!$fallback && count($categories) > 0) {
        $fallback = end($categories);
    }

    // 1. Check if user explicitly wrote a category tag
    if (!empty($explicitTag)) {
        $tagLower = mb_strtolower(trim($explicitTag), 'UTF-8');
        foreach ($categories as $cat) {
            if (mb_strtolower($cat['name'], 'UTF-8') === $tagLower || $cat['emoji'] === $explicitTag) {
                return $cat;
            }
            if (!empty($cat['keywords'])) {
                foreach ($cat['keywords'] as $kw) {
                    if (mb_strtolower($kw, 'UTF-8') === $tagLower) {
                        return $cat;
                    }
                }
            }
        }
    }

    // 2. Search inside description
    $descLower = mb_strtolower(trim($description), 'UTF-8');
    if (empty($descLower)) {
        return $fallback;
    }

    $bestCategory = $fallback;
    $bestScore = 0;

    foreach ($categories as $cat) {
        if (empty($cat['keywords'])) continue;
        $score = 0;
        foreach ($cat['keywords'] as $kw) {
            $kwLower = mb_strtolower($kw, 'UTF-8');
            if (mb_strpos($descLower, $kwLower, 0, 'UTF-8') !== false) {
                // Weight longer keyword matches higher
                $score += mb_strlen($kwLower, 'UTF-8');
            }
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestCategory = $cat;
        }
    }

    return $bestCategory;
}
