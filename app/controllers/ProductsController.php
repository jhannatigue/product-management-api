<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProductsController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->call->database();
        $this->call->library('api');
    }

    public function index()
    {
        $this->api->require_method('GET');
        $this->api->require_jwt();

        $stmt = $this->db->raw(
            "SELECT id, product_name, description, price, quantity, created_at
             FROM products
             ORDER BY id DESC"
        );

        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->api->respond([
            'status' => true,
            'products' => $products
        ]);
    }

    public function create()
    {
        $this->api->require_method('POST');
        $this->api->require_jwt();

        $data = $this->api->body();

        $product_name = trim($data['product_name'] ?? '');
        $description = trim($data['description'] ?? '');
        $price = $data['price'] ?? null;
        $quantity = $data['quantity'] ?? null;

        if ($product_name === '' || $price === null || $quantity === null) {
            $this->api->respond_error(
                'Product name, price, and quantity are required.',
                400
            );
        }

        $this->db->raw(
            "INSERT INTO products
             (product_name, description, price, quantity, created_at)
             VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)",
            [
                $product_name,
                $description,
                $price,
                $quantity
            ]
        );

        $this->api->respond([
            'status' => true,
            'message' => 'Product created successfully.'
        ], 201);
    }

    public function update($id)
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';

        if ($method !== 'PUT' && $method !== 'PATCH') {
            $this->api->respond_error(
                'Only PUT or PATCH requests are allowed.',
                405
            );
        }

        $this->api->require_jwt();

        $data = $this->api->body();

        $product_name = trim($data['product_name'] ?? '');
        $description = trim($data['description'] ?? '');
        $price = $data['price'] ?? null;
        $quantity = $data['quantity'] ?? null;

        if ($product_name === '' || $price === null || $quantity === null) {
            $this->api->respond_error(
                'Product name, price, and quantity are required.',
                400
            );
        }

        $stmt = $this->db->raw(
            "SELECT id
             FROM products
             WHERE id = ?
             LIMIT 1",
            [$id]
        );

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->api->respond_error(
                'Product not found.',
                404
            );
        }

        $this->db->raw(
            "UPDATE products
             SET product_name = ?,
                 description = ?,
                 price = ?,
                 quantity = ?
             WHERE id = ?",
            [
                $product_name,
                $description,
                $price,
                $quantity,
                $id
            ]
        );

        $this->api->respond([
            'status' => true,
            'message' => 'Product updated successfully.'
        ]);
    }

    public function delete($id)
    {
        $this->api->require_method('DELETE');
        $this->api->require_jwt();

        $stmt = $this->db->raw(
            "SELECT id
             FROM products
             WHERE id = ?
             LIMIT 1",
            [$id]
        );

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->api->respond_error(
                'Product not found.',
                404
            );
        }

        $this->db->raw(
            "DELETE FROM products
             WHERE id = ?",
            [$id]
        );

        $this->api->respond([
            'status' => true,
            'message' => 'Product deleted successfully.'
        ]);
    }
}