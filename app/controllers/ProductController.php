<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * ProductController
 *
 * CRUD for the `products` table. Every action requires a valid Bearer JWT
 * (checked through $this->api->require_jwt()); responses use the LavaLust
 * API library ($this->api->respond / respond_error).
 */
class ProductController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
    }

    /**
     * GET /api/products?search=keyword
     */
    public function index()
    {
        $this->api->require_method('GET');
        $this->api->rate_limit();
        $this->api->require_jwt();

        $search = trim((string) ($_GET['search'] ?? ''));

        $query = $this->db->table('products');
        if ($search !== '') {
            $query->like('product_name', '%' . $search . '%');
        }
        $rows = $query->order_by('id', 'DESC')->get_all();

        $this->api->respond([
            'data'  => array_map([$this, 'format'], $rows),
            'count' => count($rows),
        ]);
    }

    /**
     * GET /api/products/{id}
     */
    public function show($id)
    {
        $this->api->require_method('GET');
        $this->api->rate_limit();
        $this->api->require_jwt();

        $this->api->respond(['data' => $this->format($this->find_or_404($id))]);
    }

    /**
     * POST /api/products
     */
    public function store()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit();
        $this->api->require_jwt();

        $data = $this->validate($this->json_input());

        $this->db->table('products')->insert($data);
        $id = (int) $this->db->last_id();

        $this->api->respond([
            'message' => 'Product created successfully.',
            'data'    => $this->format($this->find_or_404($id)),
        ], 201);
    }

    /**
     * PUT|PATCH /api/products/{id}
     */
    public function update($id)
    {
        if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH'], true)) {
            $this->api->respond_error('Method Not Allowed', 405);
        }
        $this->api->rate_limit();
        $this->api->require_jwt();

        $existing = $this->find_or_404($id);
        $partial  = ($_SERVER['REQUEST_METHOD'] === 'PATCH');
        $data     = $this->validate($this->json_input(), $partial, $existing);

        if ($data) {
            $this->db->table('products')->where('id', (int) $existing['id'])->update($data);
        }

        $this->api->respond([
            'message' => 'Product updated successfully.',
            'data'    => $this->format($this->find_or_404($id)),
        ]);
    }

    /**
     * DELETE /api/products/{id}
     */
    public function destroy($id)
    {
        $this->api->require_method('DELETE');
        $this->api->rate_limit();
        $this->api->require_jwt();

        $existing = $this->find_or_404($id);
        $this->db->table('products')->where('id', (int) $existing['id'])->delete();

        $this->api->respond(['message' => 'Product deleted successfully.', 'id' => (int) $existing['id']]);
    }

    // ------------------------------------------------------------------

    private function find_or_404($id)
    {
        if (!ctype_digit((string) $id)) {
            $this->api->respond_error('Invalid product id.', 400);
        }
        $row = $this->db->table('products')->where('id', (int) $id)->get();
        if (!$row) {
            $this->api->respond_error('Product not found.', 404);
        }
        return $row;
    }

    /**
     * Validate and normalise input.
     * For PUT all fields are required; for PATCH only the supplied ones are checked.
     */
    private function validate(array $in, $partial = false, $existing = null)
    {
        $errors = [];
        $out    = [];

        // product_name
        if (!$partial || array_key_exists('product_name', $in)) {
            $name = trim((string) ($in['product_name'] ?? ''));
            if ($name === '' || $this->str_length($name) > 100) {
                $errors['product_name'] = 'Product name is required (max 100 characters).';
            }
            $out['product_name'] = $name;
        }

        // description
        if (!$partial || array_key_exists('description', $in)) {
            $desc = $in['description'] ?? null;
            $out['description'] = ($desc === null || trim((string) $desc) === '') ? null : trim((string) $desc);
        }

        // price
        if (!$partial || array_key_exists('price', $in)) {
            $price = $in['price'] ?? null;
            if (!is_numeric($price) || $price < 0 || $price > 99999999.99) {
                $errors['price'] = 'Price must be a number between 0 and 99,999,999.99.';
            }
            $out['price'] = is_numeric($price) ? number_format((float) $price, 2, '.', '') : 0;
        }

        // quantity
        if (!$partial || array_key_exists('quantity', $in)) {
            $qty = $in['quantity'] ?? null;
            if (filter_var($qty, FILTER_VALIDATE_INT) === false || (int) $qty < 0) {
                $errors['quantity'] = 'Quantity must be a whole number of 0 or more.';
            }
            $out['quantity'] = filter_var($qty, FILTER_VALIDATE_INT) === false ? 0 : (int) $qty;
        }

        if ($errors) {
            $this->api->respond(['error' => 'Validation failed', 'status' => 422, 'errors' => $errors], 422);
        }

        return $out;
    }

    /** Character count that works even when the mbstring extension is missing. */
    private function str_length($value)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }
        $count = preg_match_all('/./su', $value);
        return $count === false ? strlen($value) : $count;
    }

    private function format($row)
    {
        return [
            'id'           => (int) $row['id'],
            'product_name' => $row['product_name'],
            'description'  => $row['description'],
            'price'        => (float) $row['price'],
            'quantity'     => (int) $row['quantity'],
            'created_at'   => $row['created_at'],
        ];
    }

    private function json_input()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }
}
