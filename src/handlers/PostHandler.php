<?php
namespace src\handlers;

use \src\models\Post;
use \src\models\PostLike;
use \src\models\PostComment;
use \src\models\User;
use \src\models\UserRelation;

class PostHandler {
    public static function addPost($idUser, $type, $body) {
        $body = trim($body);
        
        if(!empty($idUser) && !empty($body)) {
            Post::insert([
                'id_user' => $idUser,
                'type' => $type,
                'created_at' => date('Y-m-d H:i:s'),
                'body' => $body
            ])->execute();
        }
    }

    public static function _postListToObject($postsList, $loggedUserId) {
        $posts = [];
        foreach($postsList as $postItem) {
            $newPost = new Post();
            $newPost->id = $postItem['id'];
            $newPost->type = $postItem['type'];
            $newPost->created_at = $postItem['created_at'];
            $newPost->body = $postItem['body'];
            $newPost->mine = false;

            if($postItem['id_user'] == $loggedUserId) {
                $newPost->mine = true;
            }

            // Preencher as informações adicionais no post
            $newUser = User::select()->where('id', $postItem['id_user'])->one();
            $newPost->user = new User();
            $newPost->user->id = $newUser['id'];
            $newPost->user->name = $newUser['name'];
            $newPost->user->avatar = $newUser['avatar'];

            // Preencher informações de Like
            $likes = PostLike::select()->where('id_post', $postItem['id'])->get();

            $newPost->likeCount = count($likes);
            $newPost->liked = self::isLiked($postItem['id'], $loggedUserId);

            // Preencher informações de Comments
            $newPost->comments = PostComment::select()->where('id_post', $postItem['id'])->get();
            foreach($newPost->comments as $key => $comment) {
                $newPost->comments[$key]['user'] = User::select()->where('id', $comment['id_user'])->one();
            }

            $posts[] = $newPost;
        }

        return $posts;
    }

    public static function isLiked($id, $loggedUserId) {
        $likes = PostLike::select()->where('id_post', $id)->get();
        $myLike = PostLike::select()->where('id_post', $id)->where('id_user', $loggedUserId)->get();

        if(count($myLike) > 0) {
            return true;
        } else {
            return false;
        }
    }

    public static function deleteLike($id, $loggedUserId) {
        PostLike::delete()->where('id_post', $id)->where('id_user', $loggedUserId)->execute();
    }

    public static function addLike($id, $loggedUserId) {
        PostLike::insert([
            'id_post' => $id,
            'id_user' => $loggedUserId,
            'created_at' => date('Y-m-d H:i:s')
        ])->execute();
    }

    public static function addComment($id, $text, $loggedUserId) {
        PostComment::insert([
            'id_post' => $id,
            'id_user' => $loggedUserId,
            'created_at' => date('Y-m-d H:i:s'),
            'body' => $text
        ])->execute();
    }

    public static function getUserFeed($idUser, $page, $loggedUserId) {
        $perPage = 2;

        $postsList = Post::select()->where('id_user', $idUser)->orderBy('created_at', 'desc')->page($page, $perPage)->get();

        $total = Post::select()->where('id_user', $idUser)->count();
        $pageCount = ceil($total /$perPage);

        // Transformar o resultado em objetos dos models
        $posts = self::_postListToObject($postsList, $loggedUserId);

        return [
            'posts' => $posts,
            'pageCount' => $pageCount,
            'currentPage' => $page
        ];
    }

    public static function getHomeFeed($idUser, $page) {
        $perPage = 2;

        // Pegar lista de usuários que o usuário segue
        $userList = UserRelation::select()->where('user_from', $idUser)->get();
        $users = [];
        foreach($userList as $userItem) {
            $users[] = $userItem['user_to'];
        }
        $users[] = $idUser;

        // Pegar os Posts dos usuários ordenado pela data
        $postsList = Post::select()->where('id_user', 'in', $users)->orderBy('created_at', 'desc')->page($page, $perPage)->get();

        $total = Post::select()->where('id_user', 'in', $users)->count();
        $pageCount = ceil($total /$perPage);

        // Transformar o resultado em objetos dos models
        $posts = self::_postListToObject($postsList, $idUser);

        // Retornar o resultado
        return [
            'posts' => $posts,
            'pageCount' => $pageCount,
            'currentPage' => $page
        ];
        
    }

    public static function getPhotosFrom($idUser) {
        $photosData = Post::select()->where('id_user', $idUser)->where('type', 'photo')->get();

        $photos = [];

        foreach($photosData as $photo) {
            $newPost = new Post();
            $newPost->id = $photo['id'];
            $newPost->type = $photo['type'];
            $newPost->created_at = $photo['created_at'];
            $newPost->body = $photo['body'];

            $photos[] = $newPost;
        }

        return $photos;
    }

    public static function delete($id, $loggedUserId) {
        // Verificar se o post existe e se é do usuário logado
        $post = Post::select()->where('id', $id)->where('id_user', $loggedUserId)->get();

        if(count($post) > 0) {
            $post = $post[0];

            // Deletar os likes e comentários do post
            PostLike::delete()->where('id_post', $id)->execute();
            PostComment::delete()->where('id_post', $id)->execute();

            // Se o post for type === photo, deletar o arquivo

            if($post['type'] === 'photo') {
                $img = __DIR__.'/../../public/media/uploads/'.$post['body'];
                if(file_exists($img)) {
                    unlink($img);
                }
            }

            // Deletar o post
            Post::delete()->where('id', $id)->execute();
        }
    }
}