# Import Zenkit Boards into Vikunja as Projects

`php artisan import:zk`

## This Code is Extremely Ugly. 
It only had to run once, so it's not really a great use of my prescious short life to fix something I'll never use again. That said, I sure ran it a lot more than once to get it to its presently functional state. Really the idea is that **you'll** only have to run it once.

I'm of two minds about sharing this. On the one hand - super ugly. On the other, I went to no small amount of trouble - might as well save somebody else that trouble.

So while I want to apologize for exposing your eyes to such a half-baked mess, this half-baked mess may very well save you quite a bit of time, in which case, you're welcome.

If you find this useful, drop me a line. 

## Notes
Except for labels, this script doesn't restart from where it left off, it'll make you brand new everything each time it runs. If it runs into a problem, it'll stop, leaving you with an incomplete dataset. When you fix the problem and run it again, you'll have a second set of data all over again. When I was testing, I put a conditional inside the part of app/Console/Commands/VikunjaFromZk.php where it actually imports the project. That allowed me to test a smaller batch. It would be pretty simple to put a config value for the project(s) you want to import and skip anything else. 

### Labels/Tags
- For my uses, the big difference between Zenkit and Vikunja is labels. Zenkit supports multiple groups of tags, then lets you use any batch of tags as list heads. All the tags in Vikunja are global, so tag groups all get mashed into one bag of tags. Zenkit defaults to one called "Stage" for column heads. This is an option in the .env file. If some of your projects/boards should be headed by Stage but others should be headed by something else, you'll run into problems. 
- In Zenkit, because you can have cards without tags, you can have cards without column heads. I didn't put in any kind of workaround for this. I fixed my data because it was easier than fixing my code.
- It won't allow two tags with the same name, even if they have different colors.

### Attachments
- This will import a maximum of five attachments. I did this because it was seg-faulting on the loop before it even got into the loop. $attachment[0]->post() (or whatever it was) worked, so I copied and pasted. I realize this is a sin. I also realize it only had to work once, which it did.
- It would be pretty cool if it would set a given uploaded image as the cover photo. I don't know if the API supports that, but it would be cool.

### Other Data Types
- Zenkit has checklists and links as their own types of objects. Vikunja does not. Append them to the end of Vikunja's description.
- The ZenKit API puts comments at the project-level and don't report the parent card's ID, so you have to do it by name.
- All projects go under parent project 0. If you're on the cloud instance, you probably don't have permissions to add to that project. It's hard-coded on app/Models/VikunjaBoard.php line 124. "It would be simple to make that a config option," you may say. You're right. Consider this something you can contribute to this software project if you're so inclined.

### Other Other
- There are places where Vikunja or Zenkit limit the number of results returned. For the most part, the fact that the data you received in the one API call may not be complete is not considered. There was only one place where it was a problem for me, so there's only one place where it's fixed.
- A lot of things are hashed by name. If you have two things of the same name, prepare to have data mashed up.
- I am the only user who matters, obviously, so I didn't keep any kind of tabs on who made a comment or card, because it's all me. 
- I also didn't keep dates 
- Sort orders? Pfft! Who needs 'em?
- This logs like it's going out of style. I like big logs and I cannot lie.

## License and Copyright
Copyright 2022-2023 KJ Coop (kj@kjcoop.com), licensed GPLv3 and beyond 

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version. 

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see <https://www.gnu.org/licenses/>. 
