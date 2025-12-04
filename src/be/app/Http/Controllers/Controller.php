<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="ATLTechCallCenterAPI API",
 *      description="Invoice management sistem API endpoints",
 * )
 *
 * @OA\Server(
 *      url="http://localhost:8000/api",
 *      description="API Server"
 * )
 *
 * @OA\PathItem(
 *      path="/api"
 * )
 *
 * @OA\SecurityScheme(
 *      type="http",
 *      description="Login with email and password to get the authentication token",
 *      scheme="bearer",
 *      bearerFormat="JWT",
 *      securityScheme="apiAuth",
 * )
 */
abstract class Controller
{
    //
}
