<?php

namespace Drupal\ai_image;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\file\Entity\File;
use Drupal\Core\Url;

/**
 * Process CSV file
 */
class GetAIImage {

  /**
   * @param Prompt $prompt
   *   The minimum 32 character prompt string
   */
  protected $prompt;

  /**
   * @param Size $size
   *   The desired image resolution - 256x256, 512x512, 1024x1024
   */
  protected $size;

  /**
   * Constructs a GetAIImage object.
   *
   */
  public function __construct() {
  }

  /**
   *
   * {@inheritdoc}
   *
   * @param String $prompt
   *   The prompt string.
   * @param String $api
   *   The image generation engine.
   * @param String $api_key
   *   API secret key.
   *
   * @return int
   *   The count of rows processed
   */
  public function getImage(string $prompt, string $api, string $api_key) {

    $img_url = $api === 'sd' ? $this->getStableDiffusionImage($prompt, $api_key)
      : $this->getOpenAIImage($prompt, $api_key);

    if ($img_url) {
      \Drupal::state()->set('recent_image', $img_url);
      \Drupal::state()->set('recent_prompt', $prompt);
      return $this->saveImageToDrupal($img_url);
    }
    return $img_url;
  }

  /**
   * Get image from Stable Diffusion API.
   *
   * @param string $prompt
   * @param string $api_key
   *
   * @return mixed
   */
  private function getStableDiffusionImage(string $prompt, string $api_key) {
    $data = '{
      "key": "' . $api_key . '",
      "prompt": "' . $prompt . '",
      "negative_prompt": "((out of frame)), ((extra fingers)), mutated hands, ((poorly drawn hands)), ((poorly drawn face)), (((mutation))), (((deformed))), (((tiling))), ((naked)), ((tile)), ((fleshpile)), ((ugly)), (((abstract))), blurry, ((bad anatomy)), ((bad proportions)), ((extra limbs)), cloned face, (((skinny))), glitchy, ((extra breasts)), ((double torso)), ((extra arms)), ((extra hands)), ((mangled fingers)), ((missing breasts)), (missing lips), ((ugly face)), ((fat)), ((extra legs)), anime",
      "width": "768",
      "height": "768",
      "samples": "1",
      "num_inference_steps": "20",
      "seed": null,
      "guidance_scale": 7.5,
      "webhook": null,
      "track_id": null
    }';

    // Set the request headers
    $headers = [
      "Content-Type: application/json",
    ];

    // Build the request URL
    $url = "https://stablediffusionapi.com/api/v3/text2img";

    // Build the CURL request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    // Execute the CURL request
    $response = curl_exec($ch);
    $decoded_response = json_decode($response);
    $img_url = $decoded_response->{'output'}[0];

    // Close the CURL connection
    curl_close($ch);
    // This was inserted to ensure larger size images were ready before
    // attempting to retrieve them.  A better way to do this would
    // be to check for 404 from the initial curl call
    sleep(5);
    return $img_url;
  }

  /**
   * Get image from the OpenAI API.
   *
   * @param string $prompt
   * @param string $api_key
   *
   * @return null
   */
  private function getOpenAIImage(string $prompt, string $api_key) {
    // Set the parameters for the request
    $model = "image-alpha-001";
    $num_images = 1;

    // Build the request URL
    $url = "https://api.openai.com/v1/images/generations";

    // Build the CURL request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);

    // Set the request headers
    $headers = [
      "Content-Type: application/json",
      "Authorization: Bearer " . $api_key,
    ];

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Set the request body
    $data = [
      "model" => $model,
      "prompt" => $prompt,
      "num_images" => $num_images,
      "size" => '1024x1024',
    ];

    $json_data = json_encode($data);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);

    // Execute the CURL request
    $response = curl_exec($ch);

    // Print the response
    $response = json_decode($response);

    // Close the CURL connection
    curl_close($ch);

    if (!empty($response->data)) {
      return $response->data[0]->url;
    }
    else {
      \Drupal::logger('ai_image')
        ->error('Can not generate image with OpenAI. Response: <pre>' . print_r($response, 1) . '</pre>');
    }
    return NULL;
  }

  private function saveImageToDrupal($image_url) {
    // Download image from URL to local server.
    $image_data = file_get_contents($image_url);
    $date = date('YmdHis', time());
    $file_name = "stabdiff_image-" . $date . ".png";
    $directory = 'public://stabdiff_images/';
    $file_path = $directory . $file_name; // Save file to public directory.

    $file_system = \Drupal::service('file_system');
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Save image file to Drupal's file system.
    $file = \Drupal::service('file.repository')
      ->writeData($image_data, $file_path, FileSystemInterface::EXISTS_REPLACE);

    // Create image entity.
    $image_path = $file->getFileUri();

    // Return the image URL within Drupal's system.
    return \Drupal::service('file_url_generator')
      ->generate($image_path)
      ->toString();
  }

}

