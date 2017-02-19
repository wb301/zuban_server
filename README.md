数据平台
     SAAS化服务，需高效，可配置，可扩展
     采用少量ORM模式代码，只使用链式默认防注入

<一 class="项目划分">
	1.Common  基础类，外部不调用
	2.Bi   数据平台-APP
	3.Admin 数据平台-工具
	4.Slt  商灵通  可拆出
</一>

<二 class="开发模式">
	1.Model->Service->Controller
	2.Model  数据访问
	3.Service  业务逻辑，数据格式层
	4.Controller  控制器，外部访问
</二>

<三 class=继承关系">
	1.每个项目都有独立  xxCommon Model, Service, Controller
	分别均继承于 Common项目下的 Common Model, Service, Controller
	2.各项目下的类，继承相应本类的 xxCommon Model, Service, Controller
	3.Common项目下的 config.php为公用配置区，项目独有配置请配置到相应项目下config.php配置
</三>

<四 class=通用API">
	1.Controller 请求方式限制    astrict_method($method)
	2.Controller 返回通用   a.成功 returnSuccess  b.失败 returnErrorNotice
	3.Model 数据访问  a.链式访问 self::DB(self::D("t_name","link"));
					b. sql查询 self::SQL(self::S("rbac_users", $sql));
</四>

<五 class="post和get划分">
     以下场景均get
     1.查找资源
     2.请求结果无持续作用，(插入，修改，删除 均有持续作用)
     3.url过长
</五>


<六 class="规范">
     1.Service->Controller 下需重构 __construct 直接初始化上级对象
     2.Controller 只做限定请求参数及返回数据
     3.Controller 统一按照 SWG格式，统一输出API文档
     4.GIT提交:添加 add  修改 update 修正 fix  删除 remove 重命名 rename
</六>

<七 class="返回状态码">
     1.status  > 0 成功  else 失败
     2.code > 0 默认100成功  <0   0~-1000 系统错误   其他 用户自定义
     3.msg成功默认success 失败则有相应提示
     4.time 请求时长  超过3S需要记录
     5.data 成功后将有数据返回
</七>


@SWG url地址：
/youfan_bi_server/index.php?c=Admin&m=Index&a=swagger
@SWG格式参考:
/**
     * @SWG\Post(
     *     path="/api/admin/organization/add",
     *     summary="新增组织",
     *     tags={"BI_USER"},
     *     description="新增组织",
     *     operationId="createOrganization",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *         name="token",
     *         in="query",
     *         description="操作人token",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Parameter(
     *         name="name",
     *         in="query",
     *         description="组织名称,组织的唯一标示 ",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Parameter(
     *         name="display_name",
     *         in="query",
     *         description="组织展示名称,可以重复 ",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Parameter(
     *         name="type",
     *         in="query",
     *         description="1目录 , 2门店",
     *         required=true,
     *         type="number",
     *     ),
     *     @SWG\Parameter(
     *         name="level",
     *         in="query",
     *         description="级别",
     *         required=true,
     *         type="number",
     *     ),
     *     @SWG\Parameter(
     *         name="parent_id",
     *         in="query",
     *         description="父级id",
     *         required=false,
     *         type="number",
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="successful operation",
     *         @SWG\Schema(
     *              @SWG\Property(property="status",  type="integer" ),

     *              @SWG\Property(property="code",  type="string", description="1000表示成功,其他失败" ),
     *              @SWG\Property(property="msg",  type="string",  ),
     *              @SWG\Property(
     *                  property="data",
     *              ),
     *
     *         ),
     *     ),
     * )
*/