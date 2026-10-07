<?php

class User
{
    private MyPDO $db;

    public function __construct(MyPDO $db)
    {
        $this->db = $db;
    }

    public function getRole(string $Role_ID): string
    {
        $sql = 'SELECT * FROM role WHERE id = :id';
        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $Role_ID
        ]);

        $role = $stmt->fetch();
        return $role['name'];
    }

    public function login(string $identifiant, string $password): bool
    {
        $sql = 'SELECT password
            FROM Utilisateur
            WHERE identifiant = :identifiant
            LIMIT 1';

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'identifiant' => $identifiant
        ]);

        $hash = $stmt->fetchColumn();

        if (!is_string($hash) || !password_verify($password, $hash)) {
            return false;
        }

        return $this->startSession($identifiant);
    }

    /** Ouvre la session sur un compte déjà authentifié : utilisé par la bascule multi-compte. */
    public function startSession(string $identifiant): bool
    {
        $sql = 'SELECT name, prenom, email, id_role, identifiant
            FROM Utilisateur
            WHERE identifiant = :identifiant
            LIMIT 1';

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'identifiant' => $identifiant
        ]);

        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        $_SESSION['email'] = $user['email'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['prenom'] = $user['prenom'];
        $_SESSION['identifiant'] = $user['identifiant'];
        $_SESSION['role_name'] = $this->getRole($user['id_role']);

        return true;
    }

    public static function buildIdentifiant(string $name, string $prenom): string
    {
        $initial = mb_strtoupper(mb_substr(trim($name), 0, 1));
        return strtolower(str_replace(' ', '', $initial . '.' . $prenom));
    }

    public function identifiantExists(string $identifiant): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM Utilisateur WHERE identifiant = :identifiant LIMIT 1'
        );
        $stmt->execute(['identifiant' => $identifiant]);
        return $stmt->fetchColumn() !== false;
    }

    public function register(
        string $name,
        string $prenom,
        int $id_role,
        string $password,
        ?string $email = null
    ): bool {
        $identifiant = self::buildIdentifiant($name, $prenom);

        if ($this->identifiantExists($identifiant)) {
            throw new DomainException('Cet identifiant existe déjà : ' . $identifiant);
        }

        $sql = "INSERT INTO Utilisateur (name, id_role, password, email, prenom, identifiant)
                VALUES (:name, :id_role, :password, :email, :prenom, :identifiant)";
        $stmt = $this->db->prepare($sql);
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $email = trim($email ?? ''); // Peut être null

        if ($email === '') {
            $email = null;
        }

        return $stmt->execute([
            'name' => $name,
            'id_role' => $id_role,
            'password' => $hash,
            'email' => $email,
            'prenom' => $prenom,
            'identifiant' => $identifiant
        ]);
    }
}
