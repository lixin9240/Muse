# TeaOrder 产品管理 API 文档

> **版本**: v1.0.0
> **基础URL**: `http://localhost:8000/api`
> **认证方式**: Bearer Token (JWT)
> **权限要求**: `role:manager` (管理员)

---

## 目录

1. [上传产品图片](#1-上传产品图片)
2. [创建产品](#2-创建产品)
3. [更新产品](#3-更新产品)
4. [删除产品](#4-删除产品)

---

## 1. 上传产品图片

上传饮品图片至 OSS 对象存储，返回图片 ID 用于后续关联产品。

### 基本信息

- **URL**: `/api/products/upload-image`
- **Method**: `POST`
- **Content-Type**: `multipart/form-data`
- **权限**: 管理员 (manager)

### 请求头

| 参数名 | 类型 | 必填 | 说明 |
|-------|------|-----|------|
| Authorization | string | ✅ | Bearer Token，格式：`Bearer {token}` |

### 请求参数 (Body - form-data)

| 参数名 | 类型 | 必填 | 说明 | 示例值 |
|-------|------|-----|------|--------|
| image | file | ✅ | 图片文件（支持 jpg/jpeg/png/webp） | `(binary)` |

#### 图片限制条件

- **大小**: ≤ 5MB (5242880 bytes)
- **格式**: image/jpeg, image/jpg, image/png, image/webp
- **尺寸**: 最小 100x100 px，最大 4096x4096 px

### 成功响应 (200)

```json
{
    "code": 200,
    "message": "图片上传成功",
    "data": {
        "image_id": 123,
        "file_url": "https://your-bucket.oss-cn-hangzhou.aliyuncs.com/products/2026/05/24/abc123_1745432100.jpg",
        "file_name": "abc123_1745432100.jpg",
        "original_name": "milktea.jpg",
        "file_size": "256.5 KB",
        "dimensions": {
            "width": 800,
            "height": 600
        },
        "mime_type": "image/jpeg",
        "uploaded_at": "2026-05-24 15:35:00"
    }
}
```

### 错误响应

#### 403 - 无权限

```json
{
    "code": 4030,
    "message": "仅管理员可操作"
}
```

#### 400 - 参数错误

**4001 - 缺少图片字段**
```json
{
    "code": 4001,
    "message": "参数错误",
    "errors": {
        "image": ["The image field is required."]
    }
}
```

**4002 - 文件过大**
```json
{
    "code": 4002,
    "message": "文件大小超过限制",
    "errors": {
        "image": ["图片大小不能超过5MB"]
    }
}
```

**4003 - 不支持的文件类型**
```json
{
    "code": 4003,
    "message": "不支持的文件类型",
    "errors": {
        "image": ["仅支持 image/jpeg, image/jpg, image/png, image/webp 格式的图片"]
    }
}
```

**4004 - 无效图片**
```json
{
    "code": 4004,
    "message": "无法读取图片信息",
    "errors": {
        "image": ["无效的图片文件"]
    }
}
```

**4005 - 尺寸过小**
```json
{
    "code": 4005,
    "message": "图片尺寸过小",
    "errors": {
        "image": ["图片尺寸不能小于 100x100"]
    }
}
```

**4006 - 尺寸过大**
```json
{
    "code": 4006,
    "message": "图片尺寸过大",
    "errors": {
        "image": ["图片尺寸不能超过 4096x4096"]
    }
}
```

#### 500 - 上传失败

```json
{
    "code": 5001,
    "message": "图片上传失败",
    "errors": {
        "image": "OSS connection timeout"
    }
}
```

### 响应参数说明

| 字段 | 类型 | 说明 |
|-----|------|------|
| data.image_id | integer | 文件记录 ID（用于创建/更新产品时关联） |
| data.file_url | string | OSS 访问 URL |
| data.file_name | string | 存储文件名（随机生成） |
| data.original_name | string | 原始文件名 |
| data.file_size | string | 格式化后的文件大小 |
| data.dimensions | object | 图片尺寸信息 |
| data.mime_type | string | MIME 类型 |
| data.uploaded_at | string | 上传时间 |

### 业务逻辑说明

1. 上传后文件的 `ref_count = 0`（未关联状态）
2. 需要在"创建产品"或"更新产品"时通过 `image_id` 关联
3. 关联后 `ref_count += 1`
4. 当产品更换图片或删除时，自动清理旧图（ref_count 归零则删除 OSS 文件）

---

## 2. 创建产品

创建新的饮品产品，包含规格、材料配置及图片关联。

### 基本信息

- **URL**: `/api/products`
- **Method**: `POST`
- **Content-Type**: `application/json`
- **权限**: 管理员 (manager)

### 请求头

| 参数名 | 类型 | 必填 | 说明 |
|-------|------|-----|------|
| Authorization | string | ✅ | Bearer Token |
| Content-Type | string | ✅ | application/json |

### 请求参数 (Body - JSON)

| 参数名 | 类型 | 必填 | 说明 | 示例值 |
|-------|------|-----|------|--------|
| category_id | integer | ✅ | 分类 ID（需存在于 categories 表） | 1 |
| name | string | ✅ | 产品名称（≤100字符） | "珍珠奶茶" |
| base_price | number | ✅ | 基础价格（≥0） | 18.00 |
| image_id | integer | ❌ | 图片 ID（来自 upload-image 接口） | 123 |
| description | string | ❌ | 产品描述（≤500字符） | "经典台式奶茶..." |
| specs | array | ✅ | 规格列表（至少1个） | 见下方 |
| materials | array | ❌ | 材料配方列表 | 见下方 |

#### specs[] 规格对象

| 参数名 | 类型 | 必填 | 说明 | 示例值 |
|-------|------|-----|------|--------|
| name | string | ✅ | 规格名称（≤50字符） | "中杯" |
| extra_price | number | ✅ | 额外价格（≥0） | 0.00 |

#### materials[] 材料对象

| 参数名 | 类型 | 必填 | 说明 | 示例值 |
|-------|------|-----|------|--------|
| material_id | integer | ✅ | 材料 ID（需存在于 materials 表） | 5 |
| quantity | number | ✅ | 用量（≥0） | 50.0 |

### 请求示例

```json
{
    "category_id": 1,
    "name": "招牌珍珠奶茶",
    "base_price": 18.00,
    "image_id": 123,
    "description": "精选红茶底 + Q弹珍珠，经典台式风味",
    "specs": [
        { "name": "中杯", "extra_price": 0 },
        { "name": "大杯", "extra_price": 3 },
        { "name": "超大杯", "extra_price": 5 }
    ],
    "materials": [
        { "material_id": 1, "quantity": 300 },
        { "material_id": 5, "quantity": 80 },
        { "material_id": 10, "quantity": 20 }
    ]
}
```

### 成功响应 (200)

```json
{
    "code": 200,
    "message": "饮品添加成功",
    "data": {
        "id": 456,
        "name": "招牌珍珠奶茶",
        "status": "active"
    }
}
```

### 错误响应

#### 403 - 无权限

```json
{
    "code": 4030,
    "message": "仅管理员可操作"
}
```

#### 400 - 参数错误

```json
{
    "code": 4001,
    "message": "参数错误",
    "errors": {
        "category_id": ["The category_id field is required."],
        "name": ["The name field is required."],
        "base_price": ["The base_price field is required."],
        "specs": ["The specs field is required."]
    }
}
```

**分类不存在**
```json
{
    "code": 4001,
    "message": "参数错误",
    "errors": {
        "category_id": ["The selected category_id is invalid."]
    }
}
```

**图片ID不存在**
```json
{
    "code": 4001,
    "message": "参数错误",
    "errors": {
        "image_id": ["The selected image_id is invalid."]
    }
}
```

### 响应参数说明

| 字段 | 类型 | 说明 |
|-----|------|------|
| data.id | integer | 新创建的产品 ID |
| data.name | string | 产品名称 |
| data.status | string | 产品状态（默认 active） |

### 业务逻辑说明

1. 使用数据库事务保证数据一致性
2. 自动创建默认 SKU（SKU-{product_id}-001）
3. 如果提供 `image_id`，自动关联图片并增加引用计数 (`ref_count += 1`)
4. 自动为每个规格创建 ProductSpec 记录
5. 材料关联到第一个 SKU

---

## 3. 更新产品

更新产品信息（名称、价格、描述、分类、状态、图片等），支持部分更新。

### 基本信息

- **URL**: `/api/products/{id}`
- **Method**: `PUT`
- **Content-Type**: `application/json`
- **权限**: 管理员 (manager)

### 路径参数

| 参数名 | 类型 | 必填 | 说明 | 示例值 |
|-------|------|-----|------|--------|
| id | integer | ✅ | 产品 ID | 456 |

### 请求头

| 参数名 | 类型 | 必填 | 说明 |
|-------|------|-----|------|
| Authorization | string | ✅ | Bearer Token |
| Content-Type | string | ✅ | application/json |

### 请求参数 (Body - JSON)

所有字段均为可选，仅传递需要更新的字段。

| 参数名 | 类型 | 必填 | 说明 | 示例值 |
|-------|------|-----|------|--------|
| name | string | ❌ | 产品名称（≤100字符） | "升级版珍珠奶茶" |
| base_price | number | ❌ | 基础价格（≥0） | 20.00 |
| description | string | ❌ | 产品描述（≤500字符） | "全新配方..." |
| category_id | integer | ❌ | 分类 ID | 2 |
| status | string | ❌ | 产品状态 | "inactive" |
| image_id | integer\|null | ❌ | 新图片 ID（传 null 清除图片） | 124 |

**status 可选值**: `active`, `inactive`

### 请求示例

#### 场景 1: 仅更新价格和描述

```json
{
    "base_price": 20.00,
    "description": "全新配方，口感更佳"
}
```

#### 场景 2: 更换产品图片

```json
{
    "image_id": 124
}
```

#### 场景 3: 清除产品图片

```json
{
    "image_id": null
}
```

#### 场景 4: 下架产品

```json
{
    "status": "inactive"
}
```

### 成功响应 (200)

```json
{
    "code": 200,
    "message": "更新成功",
    "data": {
        "id": 456,
        "category_id": 1,
        "name": "升级版珍珠奶茶",
        "base_price": 20.00,
        "image_url": "https://your-bucket.oss-cn-hangzhou.aliyuncs.com/products/2026/05/24/new_image.jpg",
        "description": "全新配方，口感更佳",
        "status": "active",
        "sort_order": 0,
        "created_at": "2026-05-24 10:00:00",
        "updated_at": "2026-05-24 16:30:00",
        "category": {
            "id": 1,
            "name": "奶茶系列"
        },
        "specs": [
            {
                "id": 789,
                "product_id": 456,
                "name": "中杯",
                "extra_price": 0,
                "sort_order": 0
            },
            {
                "id": 790,
                "product_id": 456,
                "name": "大杯",
                "extra_price": 3,
                "sort_order": 1
            }
        ]
    }
}
```

### 错误响应

#### 403 - 无权限

```json
{
    "code": 403,
    "message": "仅管理员可操作"
}
```

#### 404 - 产品不存在

```json
{
    "code": 404,
    "message": "产品不存在"
}
```

### 响应参数说明

| 字段 | 类型 | 说明 |
|-----|------|------|
| data.id | integer | 产品 ID |
| data.* | mixed | 所有产品字段（与 show 接口一致） |
| data.category | object | 分类详情 |
| data.specs | array | 规格列表 |

### 业务逻辑说明 ⭐ 核心

1. **事务保护**: 整个更新过程在数据库事务中执行
2. **图片自动清理机制**:
   ```
   检测到 image_id 变化时：
   ├─ 获取旧图片 → ref_count -= 1
   │   └─ if (ref_count == 0) → 删除 OSS 文件 + DB 记录
   │
   └─ 关联新图片 → ref_count += 1
   ```

3. **部分更新**: 只更新提供的字段，未传字段保持不变
4. **清除图片**: 传 `image_id: null` 可移除产品图片并触发旧图清理
5. **TODO 待实现**: 规格和材料的批量更新（当前仅支持基础字段）

---

## 4. 删除产品

删除指定产品，级联清理所有关联数据（规格、SKU、材料、OSS 文件）。

### 基本信息

- **URL**: `/api/products/{id}`
- **Method**: `DELETE`
- **Content-Type**: `application/json`
- **权限**: 管理员 (manager)

### 路径参数

| 参数名 | 类型 | 必填 | 说明 | 示例值 |
|-------|------|-----|------|--------|
| id | integer | ✅ | 产品 ID | 456 |

### 请求头

| 参数名 | 类型 | 必填 | 说明 |
|-------|------|-----|------|
| Authorization | string | ✅ | Bearer Token |

### 请求参数

无（路径参数已包含产品 ID）

### 请求示例

```http
DELETE /api/products/456 HTTP/1.1
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
Host: localhost:8000
```

### 成功响应 (200)

```json
{
    "code": 200,
    "message": "删除成功",
    "data": {
        "product_id": 456,
        "oss_files_cleaned": true
    }
}
```

### 错误响应

#### 403 - 无权限

```json
{
    "code": 403,
    "message": "仅管理员可操作"
}
```

#### 404 - 产品不存在

```json
{
    "code": 404,
    "message": "产品不存在"
}
```

### 响应参数说明

| 字段 | 类型 | 说明 |
|-----|------|------|
| data.product_id | integer | 已删除的产品 ID |
| data.oss_files_cleaned | boolean | 是否成功清理了 OSS 文件 |

### 业务逻辑说明 ⭐ 核心

#### 级联删除流程

```
DELETE /products/{id}
│
├─ 1. 查找产品（含 specs, skus.materials 关联）
│
├─ 2. 清理产品图片
│   ├─ FileUpload.ref_count -= 1
│   └─ if (ref_count == 0):
│       ├─ Storage::disk('oss')->delete($filePath)  ← 删除 OSS 物理文件
│       └─ FileUpload->delete()                     ← 删除 DB 记录
│
├─ 3. 删除所有规格 (ProductSpec)
│   └─ foreach ($product->specs as $spec): $spec->delete()
│
├─ 4. 删除所有 SKU 及材料关联
│   └─ foreach ($product->skus as $sku):
│       ├─ $sku->materials()->delete()              ← 删除材料关联
│       └─ $sku->delete()                           ← 删除 SKU
│
└─ 5. 删除产品本身（软删除）
    └─ $product->delete()
```

#### 数据一致性保障

- ✅ **数据库事务**: 所有删除操作在同一事务中，失败自动回滚
- ✅ **引用计数**: 通过 ref_count 机制避免误删共享图片
- ✅ **异常处理**: OSS 删除失败不影响主流程（仅记录日志）
- ✅ **软删除**: Product 模型建议开启 SoftDeletes，保留历史数据

#### 注意事项

⚠️ **不可逆操作**: 删除后数据无法恢复（除非使用软删除 + 定时任务）
⚠️ **关联影响**: 如有订单引用该产品，需提前处理业务逻辑
⚠️ **并发安全**: 高并发场景建议对产品加锁或使用乐观锁

---

## 全局错误码说明

| 错误码 | HTTP 状态码 | 说明 |
|-------|------------|------|
| 200 | 200 | 操作成功 |
| 4001 | 400 | 参数验证失败 |
| 4002 | 400 | 文件大小超限 |
| 4003 | 400 | 不支持的文件类型 |
| 4004 | 400 | 无法读取图片信息 |
| 4005 | 400 | 图片尺寸过小 |
| 4006 | 400 | 图片尺寸过大 |
| 403 / 4030 | 403 | 权限不足（非管理员） |
| 404 | 404 | 资源不存在 |
| 5001 | 500 | 服务器内部错误（如 OSS 连接失败） |

---

## 完整业务流程示例

### 场景：添加一款新饮品

```mermaid
sequenceDiagram
    participant M as Manager
    participant API as Backend API
    participant OSS as Alibaba Cloud OSS
    participant DB as Database

    M->>API: 1. POST /products/upload-image<br/>(上传奶茶图片)
    API->>API: 校验图片格式/大小/尺寸
    API->>OSS: 上传文件至 OSS
    OSS-->>API: 返回访问 URL
    API->>DB: 创建 file_uploads 记录<br/>(ref_count=0)
    API-->>M: 返回 image_id=123

    M->>API: 2. POST /products<br/>{image_id: 123, ...}
    API->>DB: 开启事务
    API->>DB: 查找 FileUpload(123)
    API->>DB: ref_count += 1 (变为1)
    API->>DB: 创建 Product 记录
    API->>DB: 创建 ProductSpecs (中杯/大杯)
    API->>DB: 创建 ProductSku
    API->>DB: 创建 ProductMaterials
    API->>DB: 提交事务
    API-->>M: 返回 product_id=456
```

### 场景：更换产品图片

```mermaid
sequenceDiagram
    participant M as Manager
    participant API as Backend API
    participant OSS as Alibaba Cloud OSS
    participant DB as Database

    M->>API: 1. POST /products/upload-image<br/>(上传新图片)
    API-->>M: 返回 new_image_id=124

    M->>API: 2. PUT /products/456<br/>{image_id: 124}
    API->>DB: 开启事务
    API->>DB: 检测到 image_id 变化 (123→124)
    
    Note over API,OSS: 清理旧图
    API->>DB: FileUpload(123).ref_count -= 1 (变为0)
    API->>OSS: DELETE products/.../old_image.jpg
    API->>DB: DELETE FileUpload(123)
    
    Note over API,DB: 关联新图
    API->>DB: FileUpload(124).ref_count += 1 (变为1)
    API->>DB: 更新 product.image_url
    
    API->>DB: 提交事务
    API-->>M: 返回更新后的产品信息
```

### 场景：删除产品

```mermaid
sequenceDiagram
    participant M as Manager
    participant API as Backend API
    participant OSS as Alibaba Cloud OSS
    participant DB as Database

    M->>API: DELETE /products/456
    API->>DB: 开启事务
    API->>DB: 加载产品 (含 specs, skus, materials)
    
    Note over API,OSS: 清理关联图片
    API->>DB: FileUpload.ref_count -= 1 (变为0)
    API->>OSS: DELETE products/.../image.jpg
    API->>DB: DELETE FileUpload record
    
    Note over API,DB: 级联删除关联数据
    API->>DB: DELETE all ProductSpecs
    loop Each SKU
        API->>DB: DELETE ProductMaterials
        API->>DB: DELETE ProductSku
    end
    API->>DB: DELETE Product (soft delete)
    
    API->>DB: 提交事务
    API-->>M: {product_id: 456, oss_files_cleaned: true}
```

---

## 附录：Apifox 导入配置

### 方式一：导入 Markdown 文档

1. 打开 Apifox 项目
2. 点击「导入」→「从 URL / 文本」
3. 选择「Markdown」格式
4. 粘贴本文档内容或上传 `.md` 文件
5. 点击「确认导入」

### 方式二：手动创建接口

按照上述文档在 Apifox 中手动创建 4 个接口：

| 序号 | 方法 | 路径 | 接口名称 |
|-----|------|------|---------|
| 1 | POST | `/products/upload-image` | 上传产品图片 |
| 2 | POST | `/products` | 创建产品 |
| 3 | PUT | `/products/{id}` | 更新产品 |
| 4 | DELETE | `/products/{id}` | 删除产品 |

### 推荐设置

- **分组**: 产品管理 (Product Management)
- **标签**: CRUD, OSS, 文件管理
- **环境变量**: 
  - `base_url`: `{{BASE_URL}}`
  - `token`: `{{JWT_TOKEN}}`

---

**文档版本**: v1.0.0  
**最后更新**: 2026-05-24  
**维护者**: TeaOrder 开发团队
