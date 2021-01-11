<?php
namespace App\Traits;

use Illuminate\Http\Response as IlluminateResponse;

/**
 * Base API Controller.
 */
Trait ResponseTrait
{
    /**
     * default status code.200
     *
     * @var int
     */
    protected $statusCode = IlluminateResponse::HTTP_OK;

    /**get the status code.
     *
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * set the status code.
     *
     * @param [type] $statusCode [description]
     *
     * @return $this
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     * Respond.
     *
     * @param array $data
     * @param array $headers
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function respond($data, $headers = [])
    {
        return response()->json($data, $this->getStatusCode(), $headers);
    }

    /**
     * respond with pagincation.
     *
     * @param array $data
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondWithPagination($data)
    {
        if (gettype($data) == 'object') {
            $data = json_decode(json_encode($data), true);
        }
        $data = [
            'status' => $this->getStatusCode(),
            'success' => true,
            'result' => $data['data'],
            'pagination' => [
                'current_page' => $data['current_page'],
                'first_page_url' => $data['first_page_url'],
                'from' =>  $data['from'],
                'last_page' => $data['last_page'],
                'last_page_url' => $data['last_page_url'],
                'next_page_url' => $data['next_page_url'],
                'path' => $data['path'],
                'per_page' => $data['per_page'],
                'prev_page_url' => $data['prev_page_url'],
                'to' => $data['to'],
                'total' => $data['total'],
            ],
        ];

        return $this->respond($data);
    }

    /**
     * Respond Created with data.201
     *
     * @param string $data
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondCreatedWithData($data)
    {
        return $this->setStatusCode(IlluminateResponse::HTTP_CREATED)->respondWithSuccess($data);
    }

    /**
     * respond with success.
     *
     * @param $data
     * @param array $headers
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondWithSuccess($data = null, $headers = [])
    {
        if (gettype($data) == 'object') {
            $data = json_decode(json_encode($data), true);
        }
        return $this->respond([
            'status' => $this->getStatusCode(),
            'success' => true,
            'result' => $data,
        ], $headers);
    }

    /**
     * respond with error.
     *
     * @param $message
     * @param array $headers
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondWithError($message, $code = null, $headers = [])
    {
        return $this->respond([
                'status' => $this->getStatusCode(),
                'success' => false,
                'error' => [
                    'code' => empty($code) ? $this->getStatusCode() : $code,
                    'message'     => $message,
                ],
            ], $headers);
    }

    /**
     * Respond not found.404
     *
     * @param string $message
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondNotFound($message = 'Not Found')
    {
        return $this->setStatusCode(IlluminateResponse::HTTP_NOT_FOUND)->respondWithError($message);
    }

    /**
     * Respond with error.500
     *
     * @param string $message
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondInternalError($message = 'Internal Error')
    {
        return $this->setStatusCode(IlluminateResponse::HTTP_INTERNAL_SERVER_ERROR)->respondWithError($message);
    }

    /**
     * Respond with unauthorized.401
     *
     * @param string $message
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondUnauthorized($message = 'Unauthorized')
    {
        return $this->setStatusCode(IlluminateResponse::HTTP_UNAUTHORIZED)->respondWithError($message);
    }

    /**
     * Respond with forbidden.403
     *
     * @param string $message
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondForbidden($message = 'Forbidden')
    {
        return $this->setStatusCode(IlluminateResponse::HTTP_FORBIDDEN)->respondWithError($message);
    }

    /**
     * Respond with no content.204
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithNoContent()
    {
        return $this->setStatusCode(IlluminateResponse::HTTP_NO_CONTENT)->respond(null);
    }

    /**
     * Respond with service unavailable.503
     *
     * @param string $message
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithServiceUnavailable($message = 'service unavailable')
    {
        return $this->setStatusCode(IlluminateResponse::HTTP_SERVICE_UNAVAILABLE)->respondWithError($message);
    }

    /**
     * Respond with method not allow.405
     *
     * @param string $message
     * @param array $headers
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithMethodNotAllow($message = 'method not allow', $headers = [])
    {
        return $this->setStatusCode(IlluminateResponse::HTTP_METHOD_NOT_ALLOWED)->respondWithError($message, $headers);
    }

    /**Note this function is same as the below function but instead of responding with error below function returns error json
     * Throw Validation.
     *
     * @param string $message
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function throwValidation($message)
    {
        return $this->setStatusCode(IlluminateResponse::HTTP_UNPROCESSABLE_ENTITY)
            ->respondWithError($message);
    }
}
