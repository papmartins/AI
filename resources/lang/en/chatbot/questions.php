<?php

return [
    'suggestions' => [
        'Movies starring Bruce Willis',
        'Movies directed by Christopher Nolan',
        'Movies starring Charlize Theron',
        'Movies with Die Hard in the title',
        'Recommend some popular movies',
        'Who directed Mad Max?',
        'Movies starring Will Ferrell',
        'Movies starring Ryan Gosling',
        'Movies with Love in the title',
        'Recommend movies to watch'
    ],
    'intents' => [
        'actor' => [
            'What movies have [actor]?',
            'Movies with [actor]',
            'Movies starring [actor]',
            'Who starred in [movie]?',
        ],
        'director' => [
            'Movies directed by [director]',
            'Who directed [movie]?',
            'Who is the director of [movie]?',
            'Films made by [director]',
        ],
        'genre' => [
            '[genre] movies',
            'Movies in the [genre] genre',
            'Recommend [genre] movies',
            'What movies are [genre]?',
        ],
        'year' => [
            'Movies from [year]',
            'Movies released in [year]',
            'Recent movies',
            'Movies from the [year]s',
        ],
        'rating' => [
            'Highly rated movies',
            'Movies with good ratings',
            'Best movies',
            'Movies with high ratings',
        ],
        'recommendation' => [
            'Recommend movies',
            'What movies do you recommend?',
            'What should I watch?',
            'Suggest popular movies',
        ],
    ],
];