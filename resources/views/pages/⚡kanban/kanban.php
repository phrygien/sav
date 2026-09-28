<?php
new class extends \Livewire\Component {

    protected $avatarColors = [
        'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald',
        'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple',
        'fuchsia', 'pink', 'rose',
    ];

    public function colorForName(string $name): string
    {
        $index = crc32($name) % count($this->avatarColors);

        return $this->avatarColors[$index];
    }

    #[\Livewire\Attributes\Computed]
    public function columns()
    {
        return [
            [
                'title' => 'Backlog',
                'cards' => [
                    [
                        'title' => 'User Reports Slow Load Times on Profile Page',
                        'badges' => [['title' => 'Bug', 'color' => 'red']],
                        'assignees' => [
                            ['name' => 'Caleb Porzio'],
                            ['name' => 'Hugo Sainte-Marie'],
                            ['name' => 'Josh Hanley'],
                        ],
                    ],
                    [
                        'title' => 'Inconsistent Button Styles on Settings Page',
                        'badges' => [['title' => 'UI', 'color' => 'blue']],
                        'assignees' => [
                            ['name' => 'Adam Wathan'],
                        ],
                    ],
                    [
                        'title' => 'Investigate Unhandled Exception on Login',
                        'badges' => [
                            ['title' => 'Bug', 'color' => 'red'],
                            ['title' => 'High priority', 'color' => 'yellow'],
                        ],
                        'assignees' => [
                            ['name' => 'Taylor Otwell'],
                            ['name' => 'Caleb Porzio'],
                        ],
                    ],
                    [
                        'title' => 'Database Migration for New Analytics Table',
                        'badges' => [['title' => 'Backend', 'color' => 'green']],
                        'assignees' => [
                            ['name' => 'Josh Hanley'],
                        ],
                    ],
                    [
                        'title' => 'Correct Misalignment of Icons in Footer',
                        'badges' => [['title' => 'UI', 'color' => 'blue']],
                        'assignees' => [
                            ['name' => 'Adam Wathan'],
                        ],
                    ],
                ],
            ],

            [
                'title' => 'Planned',
                'cards' => [
                    [
                        'title' => 'Update Privacy Policy in App',
                        'badges' => [['title' => 'UI', 'color' => 'blue']],
                        'assignees' => [
                            ['name' => 'Taylor Otwell'],
                        ],
                    ],
                    [
                        'title' => 'Fix Issue with Search Bar Auto-Suggestions',
                        'badges' => [
                            ['title' => 'Bug', 'color' => 'red'],
                            ['title' => 'UI', 'color' => 'blue'],
                        ],
                        'assignees' => [
                            ['name' => 'Caleb Porzio'],
                            ['name' => 'Hugo Sainte-Marie'],
                        ],
                    ],
                    [
                        'title' => 'Improve Loading Spinner Visuals',
                        'badges' => [['title' => 'UI', 'color' => 'blue']],
                        'assignees' => [
                            ['name' => 'Adam Wathan'],
                        ],
                    ],
                    [
                        'title' => 'Fix Date Picker Not Accepting Keyboard Input',
                        'badges' => [['title' => 'Bug', 'color' => 'red']],
                        'assignees' => [
                            ['name' => 'Taylor Otwell'],
                        ],
                    ],
                    [
                        'title' => 'Fix Permissions Issue in Admin Panel',
                        'badges' => [
                            ['title' => 'Backend', 'color' => 'green'],
                            ['title' => 'Bug', 'color' => 'red'],
                        ],
                        'assignees' => [
                            ['name' => 'Caleb Porzio'],
                            ['name' => 'Josh Hanley'],
                            ['name' => 'Adam Wathan'],
                            ['name' => 'Taylor Otwell'],
                        ],
                    ],
                    [
                        'title' => 'Resolve Broken Image Links in Product Gallery',
                        'badges' => [['title' => 'Bug', 'color' => 'red']],
                        'assignees' => [
                            ['name' => 'Adam Wathan'],
                        ],
                    ],
                ],
            ],

            [
                'title' => 'In Progress',
                'cards' => [
                    [
                        'title' => 'Responsive Improvements on Mobile',
                        'badges' => [['title' => 'UI', 'color' => 'blue']],
                        'assignees' => [
                            ['name' => 'Taylor Otwell'],
                        ],
                    ],
                    [
                        'title' => 'Fix Issue with Sorting in Data Tables',
                        'badges' => [
                            ['title' => 'Bug', 'color' => 'red'],
                            ['title' => 'UI', 'color' => 'blue'],
                        ],
                        'assignees' => [
                            ['name' => 'Caleb Porzio'],
                        ],
                    ],
                    [
                        'title' => 'Update API to Return Consistent Error Codes',
                        'badges' => [['title' => 'Backend', 'color' => 'green']],
                        'assignees' => [
                            ['name' => 'Adam Wathan'],
                        ],
                    ],
                    [
                        'title' => 'Accessibility Audit',
                        'badges' => [['title' => 'UI', 'color' => 'blue']],
                        'assignees' => [
                            ['name' => 'Taylor Otwell'],
                        ],
                    ],
                    [
                        'title' => 'UI/UX Exploration for User Dashboard',
                        'badges' => [['title' => 'UI', 'color' => 'blue']],
                        'assignees' => [
                            ['name' => 'Caleb Porzio'],
                        ],
                    ],
                ],
            ],

            [
                'title' => 'In review',
                'cards' => [
                    [
                        'title' => 'Resolve Issue with Double-Click on Buttons',
                        'badges' => [
                            ['title' => 'Bug', 'color' => 'red'],
                            ['title' => 'UI', 'color' => 'blue'],
                        ],
                        'assignees' => [
                            ['name' => 'Adam Wathan'],
                        ],
                    ],
                    [
                        'title' => 'Crash on Large File Upload',
                        'badges' => [['title' => 'High priority', 'color' => 'yellow']],
                        'assignees' => [
                            ['name' => 'Taylor Otwell'],
                        ],
                    ],
                    [
                        'title' => 'Concurrent Request Handling in API',
                        'badges' => [['title' => 'Backend', 'color' => 'green']],
                        'assignees' => [
                            ['name' => 'Caleb Porzio'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
?>
