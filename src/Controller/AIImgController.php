<?php

namespace Drupal\ai_image\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for AIImg routes.
 */
class AIImgController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

  /**
   * Builds the response.
   */
  public function getimage(Request $request): JsonResponse {
    $imgurl = NULL;
    $data = json_decode($request->getContent());
    $prompt = implode(', ', [$data->prompt, $data->options->prompt_extra]);
    $api = $data->options->source;
    $key_id = $data->options->{$api . '_key'};

    if($key_id) {
      $key = \Drupal::service('key.repository')->getKey($key_id)->getKeyValue();

      $imgurl = \Drupal::service('ai_image.get_image')
        ->getImage($prompt, $api, $key);
    }
    if (!$imgurl) {
      $imgurl = '/modules/custom/ai_image/icons/error.jpg';
    }
    return new JsonResponse(
      [
        'text' => trim($imgurl),
      ],
    );
  }


}
