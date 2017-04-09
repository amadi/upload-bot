<?php
class Image {

    var $image;
    var $image_type;

    function load($filename) {
        $image_info = getimagesize($filename);
        $this->image_type = $image_info[2];
        if( $this->image_type == IMAGETYPE_JPEG ) {
            $this->image = imagecreatefromjpeg($filename);
        } elseif( $this->image_type == IMAGETYPE_PNG ) {
            $this->image = imagecreatefrompng($filename);
        }
    }
    function save($filename, $image_type=IMAGETYPE_JPEG, $compression=75, $permissions=null) {
        if( $image_type == IMAGETYPE_JPEG ) {
            imagejpeg($this->image,$filename,$compression);
        } elseif( $image_type == IMAGETYPE_PNG ) {
            imagepng($this->image,$filename);
        }
        if( $permissions != null) {
            chmod($filename,$permissions);
        }
    }
    function output($image_type=IMAGETYPE_JPEG) {
        if( $image_type == IMAGETYPE_JPEG ) {
            imagejpeg($this->image);
        } elseif( $image_type == IMAGETYPE_PNG ) {
            imagepng($this->image);
        }
    }
    function getWidth() {
        return imagesx($this->image);
    }
    function getHeight() {
        return imagesy($this->image);
    }
    function resize($width,$height) {
        $new_image = imagecreatetruecolor($width, $height);
        $backgroundColor = imagecolorallocate($new_image, 255, 255, 255);
        imagefill($new_image, 0, 0, $backgroundColor);
        
        $src_width = $this->getWidth();
        $src_height = $this->getHeight();
        
        // Try to match destination image by width
        $new_width = $width;
        $new_height = round($new_width*($src_height/$src_width));
        $new_x = 0;
        $new_y = round(($height-$new_height)/2);
        
        $next = $new_height > $height;

        // If match by width failed and destination image does not fit, try by height
        if ($next) {
            $new_height = $height;
            $new_width = round($new_height*($src_width/$src_height));
            $new_x = round(($width - $new_width)/2);
            $new_y = 0;
        }

        // Copy image on right place
        imagecopyresampled($new_image, $this->image, $new_x, $new_y, 0, 0, $new_width, $new_height, $src_width, $src_height);
        $this->image = $new_image;
    }
}