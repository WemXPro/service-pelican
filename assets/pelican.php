<?php 

return [
    /**
     * Add locations below, each location has a name and a list of node ids
     * that belong to that location. If that location is selected, the server
     * will be deployed to one of the nodes in the list.
     */
    'locations' => [

        'germany' => [
            'name' => 'Germany', // display name
            'nodes' => [
                1,
                // add more node ids here (2, 3, 4, ...)
            ],
        ],

        // add more locations here
    ],
];