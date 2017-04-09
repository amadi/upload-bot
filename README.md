# upload-bot
## Installation:

`git clone https://github.com/amadi/upload-bot.git`

`cd upload-bot`

`composer install`

`chmod +x bot`

`./bot`

### Requirements
* PHP 7.0 or greater
* SQLite3

## Usage

`./bot command [arguments]`

Available commands:
        
        schedule        Add filenames to resize queue
        
        resize          Resize next images from the queue
        
        status          Output current tasks stats
        
        upload          Upload next images to remote storage
        
        retry           Move failed resizing tasks to queue

`./bot schedule /path_to_images_folder` - Should scan target dir for image files and place it into queue

`./bot resize [-n <count>]` - Run resizing task for <count> files, if defined

`./bot upload [-n <count>]` - Run uploading task for <count> files, if defined

`./bot retry [-n <count>]` - Place failed resizing task into queue

`./bot status` - Show tasks statistics




_Have a nice day;)_
