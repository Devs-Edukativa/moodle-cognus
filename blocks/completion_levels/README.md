# Completion Levels Block
Version: 2.0 (January 2023)

Authors:
- Astor Bizard (version 2.0+)
- Florent Paccalet (version 1)

This software is part of the Caseine project.  
This software was developped with the support of the following organizations:
- Université Grenoble Alpes
- Institut Polytechnique de Grenoble

## Introduction

Completion Levels is a block designed to track students progression within a course, and gamify their learning experience by awarding them levels as they make progress in the course.  
This block is greatly inspired from both [Level Up XP (block_xp)](https://moodle.org/plugins/block_xp) by Frédéric Massart and [Completion Progress (block_completion_progress)](https://moodle.org/plugins/block_completion_progress) by Jonathon Fowler.

## Gamification

With this block, students see progress within a course over a total or as a percentage, granting them a leveled badge. The badges are customizable (see Customization below).  
A wall of fame of students can also be displayed (optionally anonymous), to enhance the gamification process!  
![Example of a Completion Levels block as seen by a student](metadata/screenshots/student_view.png)

Multiple block instances can be present in a course. Each instance can track completion on a different set of modules, for example to track progress in different competencies.  
![Example of three block instances as seen by a student](metadata/screenshots/student_view_multiple.png)

Students can see an overview of their progress for a block, allowing easy completion tracking and fast navigation.
![Overview of student completion of three tracked modules](metadata/screenshots/student_overview.png)

Optionally, tracked modules can be highlighted by a star on the course page, allowing students to easily target them.  
![A next to a module name on the course page](metadata/screenshots/activity_marker.png)

## Setting up Completion Levels in your course

Once the block is added in your course, it needs to be configured to track completion on course modules.  
A weight can be associated to each tracked module, and will be used to compute overall block progress.  
Modules completion tracking can be done using the standard Moodle completion, or using grades. In that case, completion will be ponderated and relative to grade obtained over maximum grade for each module.  
![Block configuration form for modules](metadata/screenshots/config_activities.png)  

## Customization

Each block instance can display its own set of badges. Feel free to create your own!  
A badge set can also be defined at the site level, in the plugin administration settings.  
![Block configuration form for badge customization](metadata/screenshots/config_badges_filled.png)

## Students tracking

Teachers have access to a global overview of students block progress.  
![Overview of students completion as seen by a teacher](metadata/screenshots/overview_full.png)

They also have access to a more detailed view, listing every tracked module completion for every student.  
![Details of students completion as seen by a teacher](metadata/screenshots/details.png)

## Additional features

Notifications can be configured to be sent to some teachers, when a student achieves 100% on a block instance.  
![Block configuration form for notification](metadata/screenshots/config_notifications.png)

A Completion Levels block instance can also be added to the site front page, or on the dashboard.  
That instance will show a personal summary of Completion Levels block instances from every course the user is enrolled in.  
![The Completion Levels block on the dashboard](metadata/screenshots/dashboard.png)