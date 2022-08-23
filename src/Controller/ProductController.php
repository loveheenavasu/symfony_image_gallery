<?php
// src/Controller/LuckyController.php
namespace App\Controller;
use App\Entity\Product;
use App\Entity\Usergallery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Security\Core\Security;
class ProductController extends AbstractController
{
     public function __construct(private UrlGeneratorInterface $urlGenerator)
    {

    }
    public function create(Security $security,Request $request,ManagerRegistry $doctrine): Response
    {

        $user = $security->getUser();
            if(empty($user)){
                return new RedirectResponse($this->urlGenerator->generate('index'));
            }
        $file_error="";
        return $this->render('product/create.html.twig',array('file_error' => $file_error));
        
    }

    public function save(ManagerRegistry $doctrine, Request $request): Response
    {
        if(isset($_POST['url']) && !empty($_POST['url'])){
           $url=file_get_contents($_POST['url']);
         }
        
        if(isset($_FILES["image"]["name"]) && !empty($_FILES["image"]["name"])){
           $filename = $_FILES["image"]["name"];
        }
        
        if (!empty($url) && !empty($filename)) {
            $file_error="You can add only one image by seleting file or by url.";
            return $this->render('product/create.html.twig',array('file_error' => $file_error));

        }
        
        if(!empty($filename)){
        $tempname = $_FILES["image"]["tmp_name"];
        $time     = time();
        $folder = "uploads/".$time.$filename;
        move_uploaded_file($tempname, $folder);
        $image_name=$time.$filename;
        }

        $tags=$_POST['tags'];
        $multiple = json_decode($tags, true);
        $str = '';
        foreach($multiple as $single){
          foreach( $single as $key){
            $str .= $key.",";
               }
        }
        
        if(!empty($url)){
        $img_name = md5(uniqid()).'.png';
        $new_img=fopen('uploads/'.$img_name, 'w');
        $scrap_now=fwrite($new_img, $url);
        $image_name=$img_name;

        }
        
        $entityManager = $doctrine->getManager();
        $product = new Product();
        $product->setName($_POST['name']);
        $product->setTag($str);
        $product->setProvider($_POST['provider']);
        $product->setImage($image_name);
        $entityManager->persist($product);

        // add more products

        $entityManager->flush();
        return new RedirectResponse($this->urlGenerator->generate('gallery'));
        
    }
    
    public function gallery(Security $security,Request $request,ManagerRegistry $doctrine,PaginatorInterface $paginator): Response
        {

            $user = $security->getUser();
            if(empty($user)){
                return new RedirectResponse($this->urlGenerator->generate('index'));
            }
            if($request->get('page_num')){
                $page=$request->get('page_num');
            }else{
                $page=1;
            }
            $productRepository = $doctrine->getRepository(Product::class);
            $products = $productRepository->createQueryBuilder('p')
                        ->orderBy('p.id', 'DESC')
                        ->getQuery();
            //count of product 
            $tag="";
            $provider="";
            if(isset($_GET['tag']) || isset($_GET['provider']) ){
              $tag=$_GET['tag'];
              $provider=$_GET['provider'];
              $result = $doctrine->getRepository(Product::class)->createQueryBuilder('p')
               ->where('p.tag LIKE :tag')
               ->Andwhere('p.provider LIKE :provider')
               ->setParameter('tag','%'.$tag.'%')
               ->setParameter('provider','%'.$provider.'%')
               ->getQuery();
               $count=count($result->getResult());
               $products=$result;
            }
            
           
            $pageSize   = '20';
            $paginator  = new \Doctrine\ORM\Tools\Pagination\Paginator($products);
            $totalItems = count($paginator);
            $pagesCount = ceil($totalItems / $pageSize);
            $paginator
            ->getQuery()
            ->setFirstResult($pageSize * ($page-1)) // set the offset
            ->setMaxResults($pageSize); // set the limit 
            $products=$paginator;
            $auth_user_role = $this->getUser()->getroles();
            $auth_user_role =$auth_user_role [0];
             return $this->render('product/gallery.html.twig',array('product' => $products,'tag'=> $tag, 'provider'=> $provider,'total_pages'=>$pagesCount,'page'=>$page,'role'=>$auth_user_role));
            
        }

        public function myGallery(Request $request,ManagerRegistry $doctrine): Response
        {

          $user_gallery = new Usergallery();
          $entityManager = $doctrine->getManager();
          $product_id=$request->get('data');
          $auth_user_id = $this->getUser()->getId();
          $result = $doctrine->getRepository(Usergallery::class)->createQueryBuilder('p')
               ->where('p.user_id = :user_id')
               ->Andwhere('p.product_id = :product_id')
               ->setParameter('user_id', $auth_user_id)
               ->setParameter('product_id', $product_id)
               ->getQuery()
               ->getResult();
               if(!empty($result)){
                   return $this->json(['status'=>'false']);
            
               }
              $user_gallery->setUserId($auth_user_id);
              $user_gallery->setProductId($product_id);
              $entityManager->persist($user_gallery);
              $entityManager->flush();
            
            return $this->json(['status'=>'true']);
        }
         public function show_myGallery(Security $security,Request $request,ManagerRegistry $doctrine): Response
        {
            $user = $security->getUser();
            if(empty($user)){
                return new RedirectResponse($this->urlGenerator->generate('index'));
            }
            $auth_user_id = $this->getUser()->getId();
            $entityManager = $doctrine->getManager();
            $conn = $entityManager->getConnection();
            $sql = 'SELECT product.*
                FROM product
                INNER JOIN usergallery ON product.id = usergallery.product_id
                WHERE usergallery.user_id = '.$auth_user_id.';';
            $data = $conn->fetchAllAssociative($sql);
            return $this->render('product/my_gallery.html.twig',array('product' => $data));
            
        }
}